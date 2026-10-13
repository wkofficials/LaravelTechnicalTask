<?php

namespace Tests\Feature;

use App\Enums\DuplicatePolicy;
use App\Enums\ImportBatchStatus;
use App\Jobs\ProcessExcelImport;
use App\Models\Company;
use App\Models\ImportBatch;
use App\Models\ImportTemplate;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Tests\TestCase;

class ImportMappingTest extends TestCase
{
    use RefreshDatabase;

    private User $manager;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        Bus::fake();

        $this->manager = $this->companyUser('manager');
    }

    private function awaitingBatch(?User $uploader = null, array $headers = ['Full Name', 'E-mail', 'Mobile']): ImportBatch
    {
        return ImportBatch::factory()->awaitingConfirmation($headers, [['Jane Doe', 'jane@example.test', '0300']])->create([
            'user_id' => ($uploader ?? $this->manager)->id,
            'company_id' => $this->company->id,
        ]);
    }

    private function template(string $name, array $mapping, DuplicatePolicy $policy = DuplicatePolicy::Skip, ?Company $company = null): ImportTemplate
    {
        $template = new ImportTemplate([
            'dataset_key' => 'contacts',
            'name' => $name,
            'mapping' => $mapping,
            'duplicate_policy' => $policy,
            'schema_version' => 1,
        ]);
        $template->forceFill(['company_id' => ($company ?? $this->company)->id])->save();

        return $template;
    }

    public function test_mapping_screen_shows_suggestions_and_a_preview(): void
    {
        $batch = $this->awaitingBatch();

        $response = $this->actingAs($this->manager)->get(route('imports.show', $batch));

        $response->assertOk()
            ->assertSee('Match columns to Contacts fields')
            ->assertSee('jane@example.test');

        // "E-mail" (column 1) is suggested for Email.
        $this->assertMatchesRegularExpression(
            '/name="mapping\[email\]".*?<option value="1"\s+selected/s',
            $response->getContent()
        );
    }

    public function test_confirming_saves_mapping_and_policy_and_queues_the_import(): void
    {
        $batch = $this->awaitingBatch();

        $this->actingAs($this->manager)->post(route('imports.confirm', $batch), [
            'mapping' => ['name' => 0, 'email' => 1, 'phone' => 2, 'company' => '', 'city' => '', 'country' => ''],
            'duplicate_policy' => 'update',
        ])->assertRedirect(route('imports.show', $batch));

        $batch->refresh();
        $this->assertSame(ImportBatchStatus::Pending, $batch->status);
        $this->assertSame(['name' => 0, 'email' => 1, 'phone' => 2, 'company' => null, 'city' => null, 'country' => null], $batch->mapping);
        $this->assertSame(DuplicatePolicy::Update, $batch->duplicate_policy);

        Bus::assertDispatched(ProcessExcelImport::class, fn ($job) => $job->importBatch->is($batch));
    }

    public function test_required_fields_must_be_mapped(): void
    {
        $batch = $this->awaitingBatch();

        $this->actingAs($this->manager)->post(route('imports.confirm', $batch), [
            'mapping' => ['name' => 0, 'email' => ''],
            'duplicate_policy' => 'reject',
        ])->assertSessionHasErrors('mapping.email');

        $this->assertSame(ImportBatchStatus::AwaitingConfirmation, $batch->refresh()->status);
        Bus::assertNotDispatched(ProcessExcelImport::class);
    }

    public function test_one_column_cannot_feed_two_fields(): void
    {
        $batch = $this->awaitingBatch();

        $this->actingAs($this->manager)->post(route('imports.confirm', $batch), [
            'mapping' => ['name' => 0, 'email' => 1, 'company' => 0],
            'duplicate_policy' => 'reject',
        ])->assertSessionHasErrors('mapping.company');
    }

    public function test_columns_outside_the_file_and_unknown_policies_are_rejected(): void
    {
        $batch = $this->awaitingBatch();

        $this->actingAs($this->manager)->post(route('imports.confirm', $batch), [
            'mapping' => ['name' => 0, 'email' => 7],
            'duplicate_policy' => 'overwrite-everything',
        ])->assertSessionHasErrors(['mapping.email', 'duplicate_policy']);
    }

    public function test_fields_outside_the_dataset_are_ignored(): void
    {
        $batch = $this->awaitingBatch();

        $this->actingAs($this->manager)->post(route('imports.confirm', $batch), [
            'mapping' => ['name' => 0, 'email' => 1, 'company_id' => 2, 'password' => 2],
            'duplicate_policy' => 'reject',
        ])->assertSessionHasNoErrors();

        $this->assertSame(['name', 'email', 'phone', 'company', 'city', 'country'], array_keys($batch->refresh()->mapping));
    }

    public function test_an_import_can_only_be_confirmed_once(): void
    {
        $batch = $this->awaitingBatch();
        $payload = ['mapping' => ['name' => 0, 'email' => 1], 'duplicate_policy' => 'reject'];

        $this->actingAs($this->manager)->post(route('imports.confirm', $batch), $payload);
        $this->actingAs($this->manager)->post(route('imports.confirm', $batch), $payload)->assertForbidden();

        Bus::assertDispatchedTimes(ProcessExcelImport::class, 1);
    }

    public function test_operator_cannot_confirm_a_colleagues_import(): void
    {
        $operator = $this->companyUser('operator');
        $batch = $this->awaitingBatch($this->companyUser('operator'));

        $this->actingAs($operator)->post(route('imports.confirm', $batch), [
            'mapping' => ['name' => 0, 'email' => 1],
            'duplicate_policy' => 'reject',
        ])->assertForbidden();
    }

    public function test_confirming_can_save_a_template_by_header_name(): void
    {
        $batch = $this->awaitingBatch();

        $this->actingAs($this->manager)->post(route('imports.confirm', $batch), [
            'mapping' => ['name' => 0, 'email' => 1, 'phone' => 2],
            'duplicate_policy' => 'skip',
            'save_template' => '1',
            'template_name' => 'Supplier list',
        ])->assertSessionHasNoErrors();

        $template = ImportTemplate::sole();
        $this->assertSame('Supplier list', $template->name);
        $this->assertSame(['name' => 'Full Name', 'email' => 'E-mail', 'phone' => 'Mobile'], $template->mapping);
        $this->assertSame(DuplicatePolicy::Skip, $template->duplicate_policy);
        $this->assertSame($this->company->id, $template->company_id);
        $this->assertSame($template->id, $batch->refresh()->template_id);
    }

    public function test_operators_cannot_save_templates(): void
    {
        $operator = $this->companyUser('operator');
        $batch = $this->awaitingBatch($operator);

        $this->actingAs($operator)->post(route('imports.confirm', $batch), [
            'mapping' => ['name' => 0, 'email' => 1],
            'duplicate_policy' => 'reject',
            'save_template' => '1',
            'template_name' => 'Mine',
        ])->assertSessionHasErrors('save_template');

        $this->assertSame(0, ImportTemplate::count());
    }

    public function test_template_is_applied_even_when_columns_moved(): void
    {
        $template = $this->template('Supplier list', ['name' => 'Full Name', 'email' => 'E-mail', 'phone' => 'Mobile']);
        $batch = $this->awaitingBatch(headers: ['Mobile', 'e-mail', 'Full Name']);

        $response = $this->actingAs($this->manager)->get(route('imports.show', [$batch, 'template' => $template->id]));

        $response->assertOk()->assertDontSee('not in this file');
        $this->assertMatchesRegularExpression('/name="mapping\[name\]".*?<option value="2"\s+selected/s', $response->getContent());
        $this->assertMatchesRegularExpression('/name="mapping\[phone\]".*?<option value="0"\s+selected/s', $response->getContent());
        $this->assertMatchesRegularExpression('/id="policy-skip"\s+value="skip"\s+checked/s', $response->getContent());
    }

    public function test_template_warns_about_columns_missing_from_the_file(): void
    {
        $template = $this->template('Old layout', ['name' => 'Full Name', 'email' => 'Email Address']);
        $batch = $this->awaitingBatch();

        $this->actingAs($this->manager)->get(route('imports.show', [$batch, 'template' => $template->id]))
            ->assertOk()
            ->assertSee('These columns from the template are not in this file: Email Address.');
    }

    public function test_another_companys_template_cannot_be_used(): void
    {
        $foreign = $this->template('Theirs', ['name' => 'Full Name', 'email' => 'E-mail'], company: Company::factory()->create());
        $batch = $this->awaitingBatch();

        $this->actingAs($this->manager)->get(route('imports.show', [$batch, 'template' => $foreign->id]))
            ->assertOk()
            ->assertDontSee('Theirs');

        $this->actingAs($this->manager)->post(route('imports.confirm', $batch), [
            'mapping' => ['name' => 0, 'email' => 1],
            'duplicate_policy' => 'reject',
            'template_id' => $foreign->id,
        ])->assertSessionHasErrors('template_id');
    }

    public function test_import_awaiting_confirmation_can_be_cancelled_at_once(): void
    {
        $batch = $this->awaitingBatch();

        $this->actingAs($this->manager)->post(route('imports.cancel', $batch))->assertRedirect();

        $this->assertSame(ImportBatchStatus::Cancelled, $batch->refresh()->status);
    }
}
