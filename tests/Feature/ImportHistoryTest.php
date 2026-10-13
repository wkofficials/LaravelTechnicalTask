<?php

namespace Tests\Feature;

use App\Enums\ImportBatchStatus;
use App\Models\Company;
use App\Models\ImportBatch;
use App\Models\ImportError;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ImportHistoryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function batchFor(User $user, array $attributes = []): ImportBatch
    {
        return ImportBatch::factory()->create(['user_id' => $user->id, 'company_id' => $this->company->id] + $attributes);
    }

    private function errorFor(ImportBatch $batch, int $row, array $rowData): ImportError
    {
        $error = new ImportError();
        $error->forceFill([
            'company_id' => $batch->company_id,
            'import_batch_id' => $batch->id,
            'row_number' => $row,
            'error_code' => 'email_invalid',
            'message' => 'Invalid or missing email.',
            'row_data' => $rowData,
        ])->save();

        return $error;
    }

    public function test_operator_sees_only_their_own_imports(): void
    {
        $operator = $this->companyUser('operator');
        $colleague = $this->companyUser('operator');

        $this->batchFor($operator, ['original_filename' => 'mine.csv']);
        $this->batchFor($colleague, ['original_filename' => 'theirs.csv']);

        $this->actingAs($operator)->get(route('imports.index'))
            ->assertOk()
            ->assertSee('mine.csv')
            ->assertDontSee('theirs.csv');
    }

    public function test_manager_sees_all_company_imports(): void
    {
        $manager = $this->companyUser('manager');
        $operator = $this->companyUser('operator');

        $this->batchFor($operator, ['original_filename' => 'operator-file.csv']);

        $this->actingAs($manager)->get(route('imports.index'))
            ->assertOk()
            ->assertSee('operator-file.csv');
    }

    public function test_history_can_be_filtered_by_status(): void
    {
        $manager = $this->companyUser('manager');

        $this->batchFor($manager, ['original_filename' => 'done.csv', 'status' => ImportBatchStatus::Completed]);
        $this->batchFor($manager, ['original_filename' => 'broken.csv', 'status' => ImportBatchStatus::Failed]);

        $this->actingAs($manager)->get(route('imports.index', ['status' => 'failed']))
            ->assertOk()
            ->assertSee('broken.csv')
            ->assertDontSee('done.csv');
    }

    public function test_viewer_has_no_access_to_imports(): void
    {
        $viewer = $this->companyUser('viewer');

        $this->actingAs($viewer)->get(route('imports.index'))->assertForbidden();
    }

    public function test_details_page_lists_rejected_rows(): void
    {
        $operator = $this->companyUser('operator');
        $batch = $this->batchFor($operator, ['status' => ImportBatchStatus::CompletedWithErrors, 'failed_rows' => 1, 'processed_rows' => 1, 'started_at' => now()]);
        $this->errorFor($batch, 7, ['name' => 'Jane', 'email' => 'jane-at-example']);

        $this->actingAs($operator)->get(route('imports.show', $batch))
            ->assertOk()
            ->assertSee('Invalid or missing email.')
            ->assertSee('jane-at-example');
    }

    public function test_operator_cannot_open_a_colleagues_import(): void
    {
        $operator = $this->companyUser('operator');
        $colleague = $this->companyUser('operator');
        $batch = $this->batchFor($colleague);

        $this->actingAs($operator)->get(route('imports.show', $batch))->assertForbidden();
        $this->actingAs($operator)->get(route('imports.errors', $batch))->assertForbidden();
    }

    public function test_error_report_csv_contains_all_rows_and_neutralises_formulas(): void
    {
        $operator = $this->companyUser('operator');
        $batch = $this->batchFor($operator, ['original_filename' => 'Contacts List.xlsx', 'failed_rows' => 2]);
        $this->errorFor($batch, 3, ['name' => '=HYPERLINK("http://evil.test","click")', 'email' => 'bad']);
        $this->errorFor($batch, 9, ['name' => 'Plain Name', 'email' => '@also-bad']);

        $response = $this->actingAs($operator)->get(route('imports.errors', $batch));

        $response->assertOk();
        $response->assertDownload("contacts-list-import-{$batch->id}-errors.csv");

        $lines = array_map('str_getcsv', array_filter(explode("\n", $response->streamedContent())));

        $this->assertSame(['row_number', 'error_code', 'error', 'name', 'email'], $lines[0]);
        $this->assertCount(3, $lines);
        $this->assertSame('3', $lines[1][0]);
        $this->assertSame('\'=HYPERLINK("http://evil.test","click")', $lines[1][3]);
        $this->assertSame("'@also-bad", $lines[2][4]);
    }

    public function test_pending_import_is_cancelled_immediately(): void
    {
        $operator = $this->companyUser('operator');
        $batch = $this->batchFor($operator, ['status' => ImportBatchStatus::Pending]);

        $this->actingAs($operator)->postJson(route('imports.cancel', $batch))->assertOk();

        $batch->refresh();
        $this->assertSame(ImportBatchStatus::Cancelled, $batch->status);
        $this->assertNotNull($batch->finished_at);
    }

    public function test_running_import_gets_a_cancel_request(): void
    {
        $operator = $this->companyUser('operator');
        $batch = $this->batchFor($operator, ['status' => ImportBatchStatus::Processing]);

        $this->actingAs($operator)->post(route('imports.cancel', $batch))->assertRedirect();

        $batch->refresh();
        $this->assertSame(ImportBatchStatus::Processing, $batch->status);
        $this->assertNotNull($batch->cancel_requested_at);

        $this->actingAs($operator)->getJson(route('imports.status', $batch))
            ->assertJsonPath('cancel_requested', true)
            ->assertJsonPath('can_cancel', false);
    }

    public function test_finished_import_cannot_be_cancelled(): void
    {
        $operator = $this->companyUser('operator');
        $batch = $this->batchFor($operator, ['status' => ImportBatchStatus::Completed]);

        $this->actingAs($operator)->post(route('imports.cancel', $batch))->assertForbidden();

        $this->assertSame(ImportBatchStatus::Completed, $batch->refresh()->status);
    }

    public function test_operator_cannot_cancel_a_colleagues_import_but_manager_can(): void
    {
        $operator = $this->companyUser('operator');
        $colleague = $this->companyUser('operator');
        $manager = $this->companyUser('manager');
        $batch = $this->batchFor($colleague, ['status' => ImportBatchStatus::Processing]);

        $this->actingAs($operator)->post(route('imports.cancel', $batch))->assertForbidden();
        $this->actingAs($manager)->post(route('imports.cancel', $batch))->assertRedirect();

        $this->assertNotNull($batch->refresh()->cancel_requested_at);
    }

    public function test_another_companys_import_cannot_be_viewed_cancelled_or_downloaded(): void
    {
        $owner = $this->companyUser('owner');

        $otherCompany = Company::factory()->create();
        $outsider = $this->companyUser('owner', $otherCompany);
        $foreignBatch = ImportBatch::factory()->create([
            'company_id' => $otherCompany->id,
            'user_id' => $outsider->id,
            'status' => ImportBatchStatus::Processing,
        ]);

        $this->actingAs($owner)->get(route('imports.show', $foreignBatch))->assertNotFound();
        $this->actingAs($owner)->post(route('imports.cancel', $foreignBatch))->assertNotFound();
        $this->actingAs($owner)->get(route('imports.errors', $foreignBatch))->assertNotFound();

        $this->assertNull($foreignBatch->refresh()->cancel_requested_at);
    }

    public function test_status_returns_an_error_preview_with_spreadsheet_row_numbers(): void
    {
        $operator = $this->companyUser('operator');
        $batch = $this->batchFor($operator, ['status' => ImportBatchStatus::CompletedWithErrors, 'failed_rows' => 1, 'processed_rows' => 3, 'inserted_rows' => 2, 'total_rows' => 3]);
        $this->errorFor($batch, 4, ['email' => 'nope']);

        $this->actingAs($operator)->getJson(route('imports.status', $batch))
            ->assertOk()
            ->assertJsonPath('errors.0.row', 4)
            ->assertJsonPath('imported_rows', 2)
            ->assertJsonPath('errors_download_url', route('imports.errors', $batch));
    }
}
