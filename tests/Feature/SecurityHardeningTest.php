<?php

namespace Tests\Feature;

use App\Enums\ImportBatchStatus;
use App\Jobs\ProcessExcelImport;
use App\Models\ActivityLog;
use App\Models\CompanyInvitation;
use App\Models\ImportBatch;
use App\Models\Plan;
use App\Models\User;
use App\Notifications\ImportFinishedNotification;
use App\Support\Audit;
use App\Support\Tenancy\CurrentCompany;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Regression tests for the findings of the Phase 6 security review.
 */
class SecurityHardeningTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);

        $this->owner = $this->companyUser('owner');
    }

    // --- Finding 1: account pre-hijacking -------------------------------

    public function test_an_unverified_account_cannot_accept_an_invitation(): void
    {
        $squatter = User::factory()->unverified()->create(['email' => 'victim@corp.test']);
        [, $token] = CompanyInvitation::issue('victim@corp.test', 'manager', $this->owner);

        $this->actingAs($squatter)->post(route('invitations.accept', $token))->assertRedirect(route('verification.notice'));

        $this->assertFalse($squatter->belongsToCompany($this->company));
    }

    public function test_unverified_users_cannot_use_a_company_workspace(): void
    {
        $user = $this->companyUser('owner');
        $user->forceFill(['email_verified_at' => null])->save();

        $this->actingAs($user)->get(route('records.index', 'contacts'))->assertRedirect(route('verification.notice'));
    }

    public function test_resetting_a_password_logs_out_other_sessions(): void
    {
        $user = User::factory()->create(['password' => Hash::make('old-password')]);

        // A real login (not actingAs), so the session carries the password hash
        // — e.g. an attacker's session opened with the old password.
        $this->post('/login', ['email' => $user->email, 'password' => 'old-password']);
        $this->get(route('profile.edit'))->assertOk();

        Password::broker()->reset(
            ['email' => $user->email, 'password' => 'new-password-123', 'password_confirmation' => 'new-password-123', 'token' => Password::createToken($user)],
            fn (User $user, string $password) => $user->forceFill(['password' => Hash::make($password)])->save()
        );

        // Next request in that old session (a fresh PHP process in real life):
        // AuthenticateSession sees the password changed and logs it out.
        $this->app['auth']->forgetGuards();
        $this->get(route('profile.edit'))->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_registration_and_password_reset_requests_are_throttled(): void
    {
        foreach (range(1, 6) as $i) {
            $this->post('/forgot-password', ['email' => "nobody{$i}@example.test"]);
        }

        $this->post('/forgot-password', ['email' => 'nobody7@example.test'])->assertTooManyRequests();
    }

    // --- Finding 2: upload / analysis abuse ------------------------------

    public function test_a_company_cannot_pile_up_unconfirmed_imports(): void
    {
        Bus::fake();
        Storage::fake('local');
        ImportBatch::factory()->count(20)->awaitingConfirmation()->create(['user_id' => $this->owner->id]);

        $this->actingAs($this->owner)->postJson(route('imports.store'), [
            'dataset' => 'contacts',
            'file' => UploadedFile::fake()->create('one-more.csv', 1, 'text/csv'),
        ])->assertJsonValidationErrors('file');
    }

    public function test_a_zip_bomb_xlsx_is_rejected_before_parsing(): void
    {
        Storage::fake('local');
        $path = Storage::disk('local')->path('imports/bomb.xlsx');
        @mkdir(dirname($path), 0777, true);

        // 10 MB of zeros compresses to ~10 KB (ratio ~1000, limit is 100).
        $zip = new \ZipArchive();
        $zip->open($path, \ZipArchive::CREATE);
        $zip->addFromString('xl/worksheets/sheet1.xml', str_repeat("\0", 10 * 1024 * 1024));
        $zip->close();

        $batch = ImportBatch::factory()->create(['status' => ImportBatchStatus::Analyzing, 'file_path' => 'imports/bomb.xlsx']);
        app()->call([new \App\Jobs\AnalyzeImport($batch), 'handle']);

        $batch->refresh();
        $this->assertSame(ImportBatchStatus::Failed, $batch->status);
        $this->assertStringContainsString('could not be read', $batch->error_message);
    }

    public function test_a_csv_with_an_absurdly_wide_header_is_rejected_cheaply(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('imports/wide.csv', str_repeat('a,', 100_000)."a\n1\n");

        $batch = ImportBatch::factory()->create(['status' => ImportBatchStatus::Analyzing, 'file_path' => 'imports/wide.csv']);
        app()->call([new \App\Jobs\AnalyzeImport($batch), 'handle']);

        $batch->refresh();
        $this->assertSame(ImportBatchStatus::Failed, $batch->status);
        $this->assertStringContainsString('columns', $batch->error_message);
    }

    // --- Finding 3: quota bypass with queued imports ---------------------

    public function test_rows_of_confirmed_but_unfinished_imports_count_against_the_quota(): void
    {
        Bus::fake();
        $plan = (new Plan())->forceFill([
            'key' => 'small', 'name' => 'Small', 'max_users' => 5, 'max_rows_per_import' => 1000,
            'max_file_size_mb' => 10, 'monthly_import_rows' => 1000, 'max_templates' => 5,
        ]);
        $plan->save();
        $this->company->forceFill(['plan_id' => $plan->id])->save();

        $payload = ['mapping' => ['name' => 0, 'email' => 1], 'duplicate_policy' => 'reject'];
        $first = ImportBatch::factory()->awaitingConfirmation(['name', 'email'])->create(['total_rows' => 800, 'user_id' => $this->owner->id]);
        $second = ImportBatch::factory()->awaitingConfirmation(['name', 'email'])->create(['total_rows' => 800, 'user_id' => $this->owner->id]);

        $this->actingAs($this->owner)->post(route('imports.confirm', $first), $payload)->assertSessionHasNoErrors();
        $this->actingAs($this->owner)->post(route('imports.confirm', $second), $payload)->assertSessionHasErrors('quota');

        $this->assertSame(ImportBatchStatus::AwaitingConfirmation, $second->refresh()->status);
        Bus::assertDispatchedTimes(ProcessExcelImport::class, 1);
    }

    // --- Finding 4: removed members --------------------------------------

    public function test_a_removed_member_is_not_notified_about_the_companys_import(): void
    {
        Notification::fake();
        Storage::fake('local');
        $member = $this->companyUser('operator');
        Storage::disk('local')->put('imports/f.csv', "name,email\nJane,jane@example.test\n");
        $batch = ImportBatch::factory()->create(['file_path' => 'imports/f.csv', 'user_id' => $member->id]);

        $this->company->removeMember($member);
        app(CurrentCompany::class)->clear();

        app()->call([new ProcessExcelImport($batch), 'handle']);

        $this->assertSame(ImportBatchStatus::Completed, $batch->refresh()->status);
        Notification::assertNotSentTo($member, ImportFinishedNotification::class);
    }

    // --- Findings 5 & 6: what the audit log reveals ----------------------

    public function test_tenants_do_not_see_platform_admins_identity_or_ip(): void
    {
        $admin = User::factory()->create(['name' => 'Secret Operator']);
        $log = Audit::log('platform.plan_changed', $this->company, [], $this->company, $admin);
        $log->forceFill(['ip_address' => '203.0.113.77'])->save();

        $this->actingAs($this->owner)->get(route('audit.index'))
            ->assertOk()
            ->assertSee('Platform admin')
            ->assertDontSee('Secret Operator')
            ->assertDontSee('203.0.113.77');
    }

    public function test_audit_metadata_does_not_keep_full_emails_or_search_text(): void
    {
        Notification::fake();
        Bus::fake();

        $this->actingAs($this->owner)->post(route('team.invite'), ['email' => 'jane.doe@corp.test', 'role' => 'viewer']);
        $this->actingAs($this->owner)->post(route('records.export', 'contacts'), ['q' => 'Jane Doe']);

        $metadata = ActivityLog::pluck('metadata')->map(fn ($m) => json_encode($m))->implode(' ');

        $this->assertStringContainsString('j***@corp.test', $metadata);
        $this->assertStringNotContainsString('jane.doe@corp.test', $metadata);
        $this->assertStringNotContainsString('Jane Doe', $metadata);
    }
}
