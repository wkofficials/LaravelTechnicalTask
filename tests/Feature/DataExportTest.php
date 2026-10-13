<?php

namespace Tests\Feature;

use App\Jobs\ExportRecords;
use App\Models\Company;
use App\Models\DataExport;
use App\Models\Record;
use App\Models\User;
use App\Support\Tenancy\CurrentCompany;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DataExportTest extends TestCase
{
    use RefreshDatabase;

    private User $manager;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        Storage::fake('local');

        $this->manager = $this->companyUser('manager');
    }

    private function exportFor(User $user, array $options = [], ?Company $company = null): DataExport
    {
        $export = new DataExport(['dataset_key' => 'contacts', 'options' => $options]);
        $export->forceFill([
            'company_id' => ($company ?? $this->company)->id,
            'user_id' => $user->id,
            'status' => DataExport::STATUS_PENDING,
        ])->save();

        return $export;
    }

    private function runExport(DataExport $export): DataExport
    {
        app()->call([new ExportRecords($export), 'handle']);

        return $export->refresh();
    }

    /**
     * @return array<int, array<int, string>>
     */
    private function csv(DataExport $export): array
    {
        $content = Storage::disk('local')->get($export->file_path);
        $content = preg_replace('/^\xEF\xBB\xBF/', '', $content);

        return array_map('str_getcsv', array_values(array_filter(explode("\n", $content))));
    }

    public function test_requesting_an_export_queues_it_with_the_lists_options(): void
    {
        Bus::fake();

        $this->actingAs($this->manager)->post(route('records.export', 'contacts'), [
            'q' => 'jane',
            'filter' => ['country' => 'Pakistan', 'company_id' => 5],
            'sort' => 'password',
            'direction' => 'asc',
        ])->assertRedirect();

        $export = DataExport::sole();
        $this->assertSame($this->manager->id, $export->user_id);
        $this->assertSame(['q' => 'jane', 'filter' => ['country' => 'Pakistan'], 'sort' => 'id', 'direction' => 'asc'], $export->options);

        Bus::assertDispatched(ExportRecords::class, fn ($job) => $job->export->is($export));
    }

    public function test_operators_and_viewers_cannot_export(): void
    {
        foreach (['operator', 'viewer'] as $role) {
            $user = $this->companyUser($role);

            $this->actingAs($user)->post(route('records.export', 'contacts'))->assertForbidden();
        }
    }

    public function test_export_writes_matching_records_of_the_company_only(): void
    {
        Record::factory()->create(['name' => 'Asha', 'email' => 'asha@example.test', 'country' => 'Pakistan']);
        Record::factory()->create(['name' => 'Bilal', 'email' => 'bilal@example.test', 'country' => 'Pakistan']);
        Record::factory()->create(['name' => 'Chen', 'email' => 'chen@example.test', 'country' => 'China']);
        Record::factory()->create(['name' => 'Other Tenant', 'country' => 'Pakistan', 'company_id' => Company::factory()->create()->id]);

        // A worker that last ran for another company must not leak into this export.
        app(CurrentCompany::class)->set(Company::factory()->create());

        $export = $this->runExport($this->exportFor($this->manager, ['filter' => ['country' => 'Pakistan'], 'sort' => 'name', 'direction' => 'desc']));

        $this->assertSame(DataExport::STATUS_COMPLETED, $export->status);
        $this->assertSame(2, $export->row_count);
        $this->assertTrue($export->expires_at->isFuture());

        $rows = $this->csv($export);
        $this->assertSame(['Name', 'Email', 'Phone', 'Company', 'City', 'Country', 'Created At', 'Updated At'], $rows[0]);
        $this->assertSame(['Bilal', 'Asha'], [$rows[1][0], $rows[2][0]]);
        $this->assertCount(3, $rows);
        $this->assertStringStartsWith("exports/{$this->company->id}/", $export->file_path);
    }

    public function test_export_neutralises_spreadsheet_formulas(): void
    {
        Record::factory()->create(['name' => '=HYPERLINK("http://evil.test","x")', 'phone' => '+92-300']);

        $rows = $this->csv($this->runExport($this->exportFor($this->manager)));

        $this->assertSame('\'=HYPERLINK("http://evil.test","x")', $rows[1][0]);
        $this->assertSame("'+92-300", $rows[1][2]);
    }

    public function test_only_the_requester_can_download(): void
    {
        Record::factory()->create();
        $export = $this->runExport($this->exportFor($this->manager));
        $colleague = $this->companyUser('owner');

        $this->actingAs($this->manager)->get(route('exports.download', $export))->assertOk()->assertDownload();
        $this->actingAs($colleague)->get(route('exports.download', $export))->assertForbidden();
        $this->actingAs($colleague)->get(route('exports.status', $export))->assertForbidden();
    }

    public function test_another_companys_export_is_not_found(): void
    {
        $otherCompany = Company::factory()->create();
        $outsider = $this->companyUser('owner', $otherCompany);
        $export = $this->runExport($this->exportFor($outsider, company: $otherCompany));

        $this->actingAs($this->manager)->get(route('exports.download', $export))->assertNotFound();
    }

    public function test_status_reports_the_download_link_when_ready(): void
    {
        $export = $this->exportFor($this->manager);

        $this->actingAs($this->manager)->getJson(route('exports.status', $export))
            ->assertJsonPath('status', 'pending')
            ->assertJsonPath('download_url', null);

        $this->runExport($export);

        $this->actingAs($this->manager)->getJson(route('exports.status', $export))
            ->assertJsonPath('status', 'completed')
            ->assertJsonPath('download_url', route('exports.download', $export));
    }

    public function test_a_user_can_run_only_a_few_exports_at_once(): void
    {
        Bus::fake();

        foreach (range(1, 3) as $i) {
            $this->exportFor($this->manager);
        }

        $this->actingAs($this->manager)->post(route('records.export', 'contacts'))->assertSessionHasErrors('export');

        $this->assertSame(3, DataExport::count());
    }

    public function test_expired_exports_are_pruned_and_cannot_be_downloaded(): void
    {
        $export = $this->runExport($this->exportFor($this->manager));
        $path = $export->file_path;
        $export->forceFill(['expires_at' => now()->subMinute()])->save();

        $this->actingAs($this->manager)->get(route('exports.download', $export))->assertNotFound();

        $this->artisan('exports:prune')->assertSuccessful();

        $export->refresh();
        $this->assertSame(DataExport::STATUS_EXPIRED, $export->status);
        $this->assertNull($export->file_path);
        Storage::disk('local')->assertMissing($path);
    }

    public function test_failed_export_shows_a_safe_message(): void
    {
        $export = $this->exportFor($this->manager);

        (new ExportRecords($export))->failed(new \RuntimeException('SQLSTATE secret'));

        $export->refresh();
        $this->assertSame(DataExport::STATUS_FAILED, $export->status);
        $this->assertStringNotContainsString('SQLSTATE', $export->error_message);
        $this->assertStringContainsString("#{$export->id}", $export->error_message);
    }
}
