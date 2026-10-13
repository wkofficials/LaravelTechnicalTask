<?php

namespace Tests\Feature;

use App\Jobs\AnalyzeImport;
use App\Jobs\ProcessExcelImport;
use App\Models\ImportBatch;
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
        $viewer = $this->companyUser('viewer');

        $this->actingAs($viewer)->get(route('imports.create'))->assertForbidden();
    }

    public function test_users_with_import_permission_can_view_the_upload_form(): void
    {
        $manager = $this->companyUser('manager');

        $this->actingAs($manager)->get(route('imports.create'))->assertOk();
    }

    public function test_uploading_a_valid_excel_file_creates_a_pending_import_batch_and_queues_processing(): void
    {
        Storage::fake('local');
        Bus::fake();

        $manager = $this->companyUser('manager');

        $file = UploadedFile::fake()->create(
            'contacts.xlsx',
            500,
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
        );

        $response = $this->actingAs($manager)
            ->postJson(route('imports.store'), ['dataset' => 'contacts', 'file' => $file]);

        $response->assertOk();
        $response->assertJsonPath('batch.status', 'analyzing');

        $this->assertDatabaseHas('import_batches', [
            'original_filename' => 'contacts.xlsx',
            'dataset_key' => 'contacts',
            'status' => 'analyzing',
            'user_id' => $manager->id,
        ]);

        $batch = ImportBatch::first();
        Storage::disk('local')->assertExists($batch->file_path);

        // Only analysis is queued; nothing is imported before confirmation.
        Bus::assertDispatched(AnalyzeImport::class, fn (AnalyzeImport $job) => $job->importBatch->is($batch));
        Bus::assertNotDispatched(ProcessExcelImport::class);
    }

    public function test_unknown_dataset_is_rejected(): void
    {
        $manager = $this->companyUser('manager');

        $this->actingAs($manager)
            ->postJson(route('imports.store'), ['dataset' => 'users', 'file' => UploadedFile::fake()->create('x.csv', 1)])
            ->assertJsonValidationErrors('dataset');
    }

    public function test_viewer_cannot_upload_a_file(): void
    {
        $viewer = $this->companyUser('viewer');

        $file = UploadedFile::fake()->create('contacts.xlsx', 500);

        $this->actingAs($viewer)
            ->postJson(route('imports.store'), ['dataset' => 'contacts', 'file' => $file])
            ->assertForbidden();
    }

    public function test_semicolon_csv_detected_as_plain_text_is_accepted(): void
    {
        Bus::fake();
        $manager = $this->companyUser('manager');

        $file = UploadedFile::fake()->createWithContent('contacts.csv', "Naam;Email\nAli;ali@example.test\n");

        $this->actingAs($manager)
            ->postJson(route('imports.store'), ['dataset' => 'contacts', 'file' => $file])
            ->assertOk();
    }

    public function test_text_content_with_a_non_spreadsheet_name_is_rejected(): void
    {
        $manager = $this->companyUser('manager');

        $file = UploadedFile::fake()->createWithContent('notes.php', "<?php echo 'hi';\n");

        $this->actingAs($manager)
            ->postJson(route('imports.store'), ['dataset' => 'contacts', 'file' => $file])
            ->assertJsonValidationErrors('file');
    }

    public function test_non_excel_file_is_rejected(): void
    {
        $manager = $this->companyUser('manager');

        $file = UploadedFile::fake()->create('malware.exe', 500);

        $this->actingAs($manager)
            ->postJson(route('imports.store'), ['dataset' => 'contacts', 'file' => $file])
            ->assertJsonValidationErrors('file');
    }

    public function test_oversized_file_is_rejected(): void
    {
        $manager = $this->companyUser('manager');

        $file = UploadedFile::fake()->create(
            'huge.xlsx',
            102401,
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
        );

        $this->actingAs($manager)
            ->postJson(route('imports.store'), ['dataset' => 'contacts', 'file' => $file])
            ->assertJsonValidationErrors('file');
    }
}
