<?php

namespace Tests\Feature;

use App\Enums\ImportBatchStatus;
use App\Jobs\ExportRecords;
use App\Jobs\ProcessExcelImport;
use App\Models\Company;
use App\Models\DataExport;
use App\Models\ImportBatch;
use App\Models\User;
use App\Notifications\ExportReadyNotification;
use App\Notifications\ImportFinishedNotification;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class NotificationsTest extends TestCase
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

    private function runImport(string $csv): ImportBatch
    {
        Storage::disk('local')->put('imports/f.csv', $csv);
        $batch = ImportBatch::factory()->create(['file_path' => 'imports/f.csv', 'user_id' => $this->manager->id]);

        app()->call([new ProcessExcelImport($batch), 'handle']);

        return $batch->refresh();
    }

    public function test_uploader_is_notified_in_app_and_by_email_when_an_import_finishes(): void
    {
        Notification::fake();

        $batch = $this->runImport("name,email\nJane,jane@example.test\nBad,nope\n");

        Notification::assertSentTo($this->manager, ImportFinishedNotification::class, function ($notification, $channels) use ($batch) {
            $data = $notification->toArray($this->manager);

            return $channels === ['database', 'mail']
                && $data['title'] === "Import #{$batch->id} completed with errors"
                && str_contains($data['message'], '1 added')
                && ! str_contains($data['message'], 'jane@example.test'); // no row contents
        });
    }

    public function test_email_can_be_turned_off_per_company(): void
    {
        Notification::fake();
        $this->company->forceFill(['email_import_notifications' => false])->save();

        $this->runImport("name,email\nJane,jane@example.test\n");

        Notification::assertSentTo($this->manager, ImportFinishedNotification::class, fn ($n, $channels) => $channels === ['database']);
    }

    public function test_failed_import_notifies_once_and_a_late_failure_after_cancel_does_not(): void
    {
        Notification::fake();
        $batch = ImportBatch::factory()->processing()->create(['user_id' => $this->manager->id]);
        $cancelled = ImportBatch::factory()->create(['user_id' => $this->manager->id, 'status' => ImportBatchStatus::Cancelled]);

        (new ProcessExcelImport($batch))->failed(new \RuntimeException('boom'));
        (new ProcessExcelImport($batch))->failed(new \RuntimeException('boom again'));
        (new ProcessExcelImport($cancelled))->failed(new \RuntimeException('late'));

        Notification::assertSentToTimes($this->manager, ImportFinishedNotification::class, 1);
    }

    public function test_requester_is_notified_when_an_export_is_ready(): void
    {
        Notification::fake();
        $export = new DataExport(['dataset_key' => 'contacts', 'options' => []]);
        $export->forceFill(['user_id' => $this->manager->id, 'status' => DataExport::STATUS_PENDING])->save();

        app()->call([new ExportRecords($export), 'handle']);

        Notification::assertSentTo($this->manager, ExportReadyNotification::class);
    }

    public function test_bell_shows_unread_notifications(): void
    {
        $this->runImport("name,email\nJane,jane@example.test\n");

        $this->actingAs($this->manager)->get(route('dashboard'))
            ->assertOk()
            ->assertSee('1 unread')
            ->assertSee('completed');
    }

    public function test_opening_a_notification_marks_it_read_and_switches_to_its_company(): void
    {
        $batch = $this->runImport("name,email\nJane,jane@example.test\n");
        $notification = $this->manager->notifications()->sole();

        // The user has meanwhile switched to another company they belong to.
        $other = Company::factory()->create();
        $other->addMember($this->manager, 'viewer');
        $this->manager->switchCompany($other);

        $this->actingAs($this->manager)->get(route('notifications.open', $notification->id))
            ->assertRedirect(route('imports.show', $batch, absolute: false));

        $this->assertNotNull($notification->fresh()->read_at);
        $this->assertSame($this->company->id, $this->manager->fresh()->current_company_id);
    }

    public function test_notification_links_never_redirect_off_site(): void
    {
        $this->manager->notifications()->create([
            'id' => (string) \Illuminate\Support\Str::uuid(),
            'type' => 'test',
            'data' => ['title' => 'x', 'url' => '//evil.test/phish'],
        ]);
        $notification = $this->manager->notifications()->sole();

        $this->actingAs($this->manager)->get(route('notifications.open', $notification->id))
            ->assertRedirect(route('notifications.index'));
    }

    public function test_users_cannot_open_someone_elses_notification(): void
    {
        $this->runImport("name,email\nJane,jane@example.test\n");
        $notification = $this->manager->notifications()->sole();

        $this->actingAs($this->companyUser('owner'))->get(route('notifications.open', $notification->id))->assertNotFound();
        $this->assertNull($notification->fresh()->read_at);
    }
}
