<?php

namespace Tests\Feature;

use App\Jobs\ProcessExcelImport;
use App\Models\ImportBatch;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ImportUploadTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_guests_are_redirected_to_login(): void
    {
        $this->get(route('imports.create'))->assertRedirect(route('login'));
    }

    public function test_users_without_import_permission_are_forbidden(): void
    {
        $viewer = User::factory()->create();
        $viewer->assignRole('viewer');

        $this->actingAs($viewer)->get(route('imports.create'))->assertForbidden();
    }

    public function test_users_with_import_permission_can_view_the_upload_form(): void
    {
        $manager = User::factory()->create();
        $manager->assignRole('manager');

        $this->actingAs($manager)->get(route('imports.create'))->assertOk();
    }

    public function test_uploading_a_valid_excel_file_creates_a_pending_import_batch_and_queues_processing(): void
    {
        Storage::fake('local');
        Bus::fake();

        $manager = User::factory()->create();
        $manager->assignRole('manager');

        $file = UploadedFile::fake()->create(
            'contacts.xlsx',
            500,
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
        );

        $response = $this->actingAs($manager)
            ->postJson(route('imports.store'), ['file' => $file]);

        $response->assertOk();
        $response->assertJsonPath('batch.status', 'pending');

        $this->assertDatabaseHas('import_batches', [
            'original_filename' => 'contacts.xlsx',
            'status' => 'pending',
            'user_id' => $manager->id,
        ]);

        $batch = ImportBatch::first();
        Storage::disk('local')->assertExists($batch->file_path);

        Bus::assertDispatched(
            ProcessExcelImport::class,
            fn (ProcessExcelImport $job) => $job->importBatch->is($batch)
        );
    }

    public function test_viewer_cannot_upload_a_file(): void
    {
        $viewer = User::factory()->create();

        $viewer->assignRole('viewer');

        $file = UploadedFile::fake()->create('contacts.xlsx', 500);

        $this->actingAs($viewer)
            ->postJson(route('imports.store'), ['file' => $file])
            ->assertForbidden();
    }

    public function test_non_excel_file_is_rejected(): void
    {
        $manager = User::factory()->create();
        $manager->assignRole('manager');

        $file = UploadedFile::fake()->create('malware.exe', 500);

        $this->actingAs($manager)
            ->postJson(route('imports.store'), ['file' => $file])
            ->assertJsonValidationErrors('file');
    }

    public function test_oversized_file_is_rejected(): void
    {
        $manager = User::factory()->create();
        $manager->assignRole('manager');

        $file = UploadedFile::fake()->create(
            'huge.xlsx',
            102401,
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
        );

        $this->actingAs($manager)
            ->postJson(route('imports.store'), ['file' => $file])
            ->assertJsonValidationErrors('file');
    }
}
