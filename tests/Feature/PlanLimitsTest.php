<?php

namespace Tests\Feature;

use App\Enums\ImportBatchStatus;
use App\Jobs\AnalyzeImport;
use App\Jobs\ProcessExcelImport;
use App\Models\CompanyInvitation;
use App\Models\ImportBatch;
use App\Models\ImportTemplate;
use App\Models\Plan;
use App\Models\UsageRecord;
use App\Models\User;
use App\Notifications\UsageWarningNotification;
use App\Support\PlanLimits;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PlanLimitsTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private Plan $plan;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        Storage::fake('local');

        $this->owner = $this->companyUser('owner');

        // A small plan so limits are easy to reach (the migration seeds the real ones).
        $this->plan = (new Plan())->forceFill([
            'key' => 'tiny', 'name' => 'Tiny', 'max_users' => 2, 'max_rows_per_import' => 5,
            'max_file_size_mb' => 1, 'monthly_import_rows' => 10, 'max_templates' => 1,
        ]);
        $this->plan->save();
        $this->company->forceFill(['plan_id' => $this->plan->id])->save();
    }

    private function usage(int $amount, string $key = 'manual'): void
    {
        $record = new UsageRecord();
        $record->forceFill([
            'company_id' => $this->company->id,
            'period' => PlanLimits::for($this->company)->period(),
            'metric' => UsageRecord::METRIC_IMPORT_ROWS,
            'amount' => $amount,
            'idempotency_key' => $key,
        ])->save();
    }

    public function test_companies_without_a_plan_get_the_default_plan(): void
    {
        $this->company->forceFill(['plan_id' => null])->save();

        $this->assertSame('free', PlanLimits::for($this->company->fresh())->plan()->key);
    }

    public function test_upload_larger_than_the_plan_allows_is_rejected(): void
    {
        Bus::fake();

        $this->actingAs($this->owner)->postJson(route('imports.store'), [
            'dataset' => 'contacts',
            'file' => UploadedFile::fake()->create('big.csv', 2048, 'text/csv'),
        ])->assertJsonValidationErrors(['file' => 'Your plan allows files up to 1 MB.']);
    }

    public function test_file_with_more_rows_than_the_plan_allows_fails_at_analysis(): void
    {
        $rows = collect(range(1, 6))->map(fn ($i) => "P{$i},p{$i}@example.test")->implode("\n");
        Storage::disk('local')->put('imports/f.csv', "name,email\n{$rows}\n");
        $batch = ImportBatch::factory()->create(['status' => ImportBatchStatus::Analyzing, 'file_path' => 'imports/f.csv']);

        app()->call([new AnalyzeImport($batch), 'handle']);

        $batch->refresh();
        $this->assertSame(ImportBatchStatus::Failed, $batch->status);
        $this->assertStringContainsString('up to 5 rows per import', $batch->error_message);
    }

    public function test_confirm_is_blocked_when_the_monthly_allowance_would_be_exceeded(): void
    {
        Bus::fake();
        $this->usage(8);
        $batch = ImportBatch::factory()->awaitingConfirmation(['name', 'email'])->create(['total_rows' => 3, 'user_id' => $this->owner->id]);

        $this->actingAs($this->owner)->post(route('imports.confirm', $batch), [
            'mapping' => ['name' => 0, 'email' => 1],
            'duplicate_policy' => 'reject',
        ])->assertSessionHasErrors('quota');

        Bus::assertNotDispatched(ProcessExcelImport::class);
    }

    public function test_imported_rows_are_metered_once_per_chunk(): void
    {
        Storage::disk('local')->put('imports/f.csv', "name,email\nA,a@example.test\nB,b@example.test\nBad,nope\n");
        $batch = ImportBatch::factory()->create(['file_path' => 'imports/f.csv']);

        app()->call([new ProcessExcelImport($batch), 'handle']);
        // Re-delivered job: everything is already committed.
        ImportBatch::whereKey($batch->id)->update(['status' => ImportBatchStatus::Processing->value]);
        app()->call([new ProcessExcelImport($batch), 'handle']);

        $this->assertSame(2, PlanLimits::for($this->company)->importRowsUsed(), 'rejected rows are not counted, retries are not double-counted');
    }

    public function test_usage_period_follows_the_company_timezone(): void
    {
        $this->travelTo(now('UTC')->setDate(2026, 10, 31)->setTime(22, 0));

        $this->company->forceFill(['timezone' => 'Asia/Karachi'])->save(); // UTC+5: already Nov 1st
        $this->assertSame('2026-11', PlanLimits::for($this->company)->period());

        $this->company->forceFill(['timezone' => 'UTC'])->save();
        $this->assertSame('2026-10', PlanLimits::for($this->company)->period());
    }

    public function test_invitations_stop_at_the_user_limit_but_re_invites_are_allowed(): void
    {
        Notification::fake();

        // Owner + 1 pending invitation = 2 of 2 seats.
        $this->actingAs($this->owner)->post(route('team.invite'), ['email' => 'one@example.test', 'role' => 'viewer'])->assertSessionHasNoErrors();
        $this->actingAs($this->owner)->post(route('team.invite'), ['email' => 'two@example.test', 'role' => 'viewer'])
            ->assertSessionHasErrors(['email' => 'Your plan allows 2 users (members plus pending invitations). Remove someone or upgrade your plan.']);

        $this->actingAs($this->owner)->post(route('team.invite'), ['email' => 'one@example.test', 'role' => 'manager'])->assertSessionHasNoErrors();

        $this->assertSame(['one@example.test'], CompanyInvitation::pluck('email')->all());
    }

    public function test_templates_stop_at_the_plan_limit(): void
    {
        Bus::fake();
        $existing = new ImportTemplate(['dataset_key' => 'contacts', 'name' => 'First', 'mapping' => [], 'duplicate_policy' => 'reject', 'schema_version' => 1]);
        $existing->save();

        $batch = ImportBatch::factory()->awaitingConfirmation(['name', 'email'])->create(['total_rows' => 1, 'user_id' => $this->owner->id]);

        $this->actingAs($this->owner)->post(route('imports.confirm', $batch), [
            'mapping' => ['name' => 0, 'email' => 1],
            'duplicate_policy' => 'reject',
            'save_template' => '1',
            'template_name' => 'Second',
        ])->assertSessionHasErrors(['save_template' => 'Your plan allows 1 saved templates. Delete one or upgrade your plan.']);
    }

    public function test_owners_are_warned_once_when_passing_eighty_percent(): void
    {
        Notification::fake();
        $manager = $this->companyUser('manager');

        $this->usage(7, 'a');
        PlanLimits::for($this->company)->warnIfNearMonthlyLimit();
        Notification::assertNothingSent();

        $this->usage(1, 'b'); // 8 of 10
        PlanLimits::for($this->company)->warnIfNearMonthlyLimit();
        PlanLimits::for($this->company)->warnIfNearMonthlyLimit();

        Notification::assertSentToTimes($this->owner, UsageWarningNotification::class, 1);
        Notification::assertNotSentTo($manager, UsageWarningNotification::class);
    }

    public function test_usage_page_shows_plan_and_meters(): void
    {
        $this->usage(4);

        $this->actingAs($this->owner)->get(route('usage'))
            ->assertOk()
            ->assertSee('Tiny plan')
            ->assertSeeInOrder(['Imported rows per month', '4 / 10']);
    }

    public function test_operators_cannot_see_usage(): void
    {
        $this->actingAs($this->companyUser('operator'))->get(route('usage'))->assertForbidden();
    }
}
