<?php

namespace Tests\Feature;

use App\Enums\ImportBatchStatus;
use App\Imports\DatasetImport;
use App\Jobs\ProcessExcelImport;
use App\Models\ImportBatch;
use App\Models\ImportChunk;
use App\Models\Record;
use App\Models\UsageRecord;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx as XlsxWriter;
use Tests\TestCase;

class ProcessExcelImportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
    }

    public function test_processes_a_valid_csv_file_and_creates_records(): void
    {
        $csv = "name,email,phone,company,city,country\n"
            ."Jane Doe,jane.doe@example.test,555-0100,Acme Corp,Springfield,USA\n"
            ."John Smith,john.smith@example.test,555-0101,Globex Inc,Shelbyville,USA\n";

        Storage::disk('local')->put('imports/valid.csv', $csv);

        $batch = ImportBatch::factory()->create([
            'file_path' => 'imports/valid.csv',
            'original_filename' => 'valid.csv',
        ]);

        app()->call([new ProcessExcelImport($batch), 'handle']);

        $batch->refresh();

        $this->assertSame(ImportBatchStatus::Completed, $batch->status);
        $this->assertSame(2, $batch->total_rows);
        $this->assertSame(2, $batch->processed_rows);
        $this->assertSame(0, $batch->failed_rows);
        $this->assertNotNull($batch->started_at);
        $this->assertNotNull($batch->finished_at);

        $this->assertSame(2, Record::where('import_batch_id', $batch->id)->count());
        $this->assertDatabaseHas('records', [
            'import_batch_id' => $batch->id,
            'name' => 'Jane Doe',
            'email' => 'jane.doe@example.test',
            'company' => 'Acme Corp',
        ]);
    }

    public function test_skips_invalid_rows_and_records_the_errors(): void
    {
        $csv = "name,email,phone,company,city,country\n"
            ."Jane Doe,jane.doe@example.test,555-0100,Acme Corp,Springfield,USA\n"
            .",missing-name@example.test,555-0101,Globex Inc,Shelbyville,USA\n"
            ."Bad Email,not-an-email,555-0102,Initech,Capital City,USA\n";

        Storage::disk('local')->put('imports/partial.csv', $csv);

        $batch = ImportBatch::factory()->create([
            'file_path' => 'imports/partial.csv',
            'original_filename' => 'partial.csv',
        ]);

        app()->call([new ProcessExcelImport($batch), 'handle']);

        $batch->refresh();

        $this->assertSame(ImportBatchStatus::CompletedWithErrors, $batch->status);
        $this->assertSame(3, $batch->total_rows);
        $this->assertSame(3, $batch->processed_rows);
        $this->assertSame(2, $batch->failed_rows);

        // Row numbers are spreadsheet rows: heading is row 1, data starts at row 2.
        $this->assertSame(
            [[3, 'name_required'], [4, 'email_invalid']],
            $batch->errors()->orderBy('row_number')->get()->map(fn ($e) => [$e->row_number, $e->error_code])->all()
        );
        $this->assertSame('not-an-email', $batch->errors()->where('row_number', 4)->first()->row_data['email']);

        $this->assertSame(1, Record::where('import_batch_id', $batch->id)->count());
    }

    public function test_processes_a_real_xlsx_file_and_detects_total_rows(): void
    {
        $spreadsheet = new Spreadsheet();
        $spreadsheet->getActiveSheet()->fromArray([
            ['name', 'email', 'phone', 'company', 'city', 'country'],
            ['Jane Doe', 'jane.doe@example.test', '555-0100', 'Acme Corp', 'Springfield', 'USA'],
            ['John Smith', 'john.smith@example.test', '555-0101', 'Globex Inc', 'Shelbyville', 'USA'],
            ['Ann Lee', 'ann.lee@example.test', '555-0102', 'Initech', 'Capital City', 'USA'],
        ], null, 'A1');

        $tmpPath = tempnam(sys_get_temp_dir(), 'xlsx_test_').'.xlsx';
        (new XlsxWriter($spreadsheet))->save($tmpPath);
        Storage::disk('local')->put('imports/valid.xlsx', file_get_contents($tmpPath));
        unlink($tmpPath);

        $batch = ImportBatch::factory()->create([
            'file_path' => 'imports/valid.xlsx',
            'original_filename' => 'valid.xlsx',
        ]);

        app()->call([new ProcessExcelImport($batch), 'handle']);

        $batch->refresh();

        $this->assertSame(ImportBatchStatus::Completed, $batch->status);
        $this->assertSame(3, $batch->total_rows);
        $this->assertSame(3, $batch->processed_rows);
        $this->assertSame(3, Record::where('import_batch_id', $batch->id)->count());
    }

    public function test_marks_batch_failed_when_file_is_missing_from_disk(): void
    {
        $batch = ImportBatch::factory()->create([
            'file_path' => 'imports/does-not-exist.csv',
            'original_filename' => 'does-not-exist.csv',
        ]);

        app()->call([new ProcessExcelImport($batch), 'handle']);

        $batch->refresh();

        $this->assertSame(ImportBatchStatus::Failed, $batch->status);
        $this->assertNotNull($batch->error_message);
    }

    public function test_marks_batch_failed_when_file_content_is_corrupt(): void
    {
        Storage::disk('local')->put('imports/corrupt.xlsx', 'this is not a real spreadsheet');

        $batch = ImportBatch::factory()->create([
            'file_path' => 'imports/corrupt.xlsx',
            'original_filename' => 'corrupt.xlsx',
        ]);

        // An unreadable file fails straight away (no pointless retries) with
        // a message that is safe to show — no exception text or file paths.
        app()->call([new ProcessExcelImport($batch), 'handle']);

        $batch->refresh();

        $this->assertSame(ImportBatchStatus::Failed, $batch->status);
        $this->assertStringContainsString('could not be read', $batch->error_message);
        $this->assertStringNotContainsString('imports/', $batch->error_message);
    }

    public function test_more_than_a_hundred_row_errors_are_all_kept(): void
    {
        $csv = "name,email\n".str_repeat("No Email,\n", 150);
        Storage::disk('local')->put('imports/bad.csv', $csv);

        $batch = ImportBatch::factory()->create(['file_path' => 'imports/bad.csv']);

        app()->call([new ProcessExcelImport($batch), 'handle']);

        $this->assertSame(150, $batch->refresh()->failed_rows);
        $this->assertSame(150, $batch->errors()->count());
    }

    public function test_a_retry_skips_chunks_that_were_already_committed(): void
    {
        $rows = collect(range(1, DatasetImport::CHUNK_SIZE + 5))
            ->map(fn ($i) => "Person {$i},person{$i}@example.test")
            ->implode("\n");
        Storage::disk('local')->put('imports/big.csv', "name,email\n{$rows}\n");

        $batch = ImportBatch::factory()->create(['file_path' => 'imports/big.csv']);

        app()->call([new ProcessExcelImport($batch), 'handle']);

        // Rewind to the state of a worker that crashed after committing chunk 1
        // but before committing chunk 2 (chunk 2 rolled back as a whole).
        $secondChunkStart = DatasetImport::CHUNK_SIZE + 2;
        ImportChunk::where('import_batch_id', $batch->id)->where('start_row', $secondChunkStart)->delete();
        UsageRecord::where('idempotency_key', "import-rows:{$batch->id}:{$secondChunkStart}")->delete();
        Record::where('import_batch_id', $batch->id)
            ->whereIn('email', collect(range(DatasetImport::CHUNK_SIZE + 1, DatasetImport::CHUNK_SIZE + 5))->map(fn ($i) => "person{$i}@example.test"))
            ->delete();
        ImportBatch::whereKey($batch->id)->update([
            'status' => ImportBatchStatus::Processing->value,
            'processed_rows' => DatasetImport::CHUNK_SIZE,
            'finished_at' => null,
        ]);

        // The retry.
        app()->call([new ProcessExcelImport($batch), 'handle']);

        $batch->refresh();
        $this->assertSame(ImportBatchStatus::Completed, $batch->status);
        $this->assertSame(DatasetImport::CHUNK_SIZE + 5, $batch->processed_rows);
        $this->assertSame(DatasetImport::CHUNK_SIZE + 5, Record::where('import_batch_id', $batch->id)->count());
        $this->assertSame(DatasetImport::CHUNK_SIZE + 5, Record::where('import_batch_id', $batch->id)->distinct()->count('email'));
        $this->assertSame(DatasetImport::CHUNK_SIZE + 5, (int) UsageRecord::where('import_batch_id', $batch->id)->sum('amount'), 'usage counted once');
    }

    public function test_running_the_same_job_twice_does_not_duplicate_anything(): void
    {
        Storage::disk('local')->put('imports/valid.csv', "name,email\nJane,jane@example.test\nJohn,john@example.test\n");

        $batch = ImportBatch::factory()->create(['file_path' => 'imports/valid.csv']);

        app()->call([new ProcessExcelImport($batch), 'handle']);

        // e.g. the queue re-delivers a job whose worker died after finishing.
        ImportBatch::whereKey($batch->id)->update(['status' => ImportBatchStatus::Processing->value]);
        app()->call([new ProcessExcelImport($batch), 'handle']);

        $this->assertSame(2, Record::where('import_batch_id', $batch->id)->count());
        $this->assertSame(2, $batch->refresh()->processed_rows);
    }

    public function test_a_batch_cancelled_while_queued_is_not_processed(): void
    {
        Storage::disk('local')->put('imports/valid.csv', "name,email\nJane,jane@example.test\n");

        $batch = ImportBatch::factory()->create([
            'file_path' => 'imports/valid.csv',
            'status' => ImportBatchStatus::Cancelled,
        ]);

        app()->call([new ProcessExcelImport($batch), 'handle']);

        $this->assertSame(ImportBatchStatus::Cancelled, $batch->refresh()->status);
        $this->assertSame(0, Record::where('import_batch_id', $batch->id)->count());
    }

    public function test_cancel_request_stops_processing_before_the_next_chunk_and_keeps_committed_rows(): void
    {
        $rows = collect(range(1, DatasetImport::CHUNK_SIZE * 2))
            ->map(fn ($i) => "Person {$i},person{$i}@example.test")
            ->implode("\n");
        Storage::disk('local')->put('imports/big.csv', "name,email\n{$rows}\n");

        $batch = ImportBatch::factory()->create(['file_path' => 'imports/big.csv']);

        // Request cancellation the moment the first chunk is being committed.
        $requested = false;
        DB::listen(function ($query) use ($batch, &$requested) {
            if (! $requested && str_contains($query->sql, 'insert into "import_chunks"')) {
                $requested = true;
                ImportBatch::whereKey($batch->id)->update(['cancel_requested_at' => now()]);
            }
        });

        app()->call([new ProcessExcelImport($batch), 'handle']);

        $batch->refresh();
        $this->assertSame(ImportBatchStatus::Cancelled, $batch->status);
        $this->assertSame(DatasetImport::CHUNK_SIZE, Record::where('import_batch_id', $batch->id)->count());
        $this->assertStringContainsString((string) DatasetImport::CHUNK_SIZE, $batch->error_message);
    }

    public function test_final_failure_shows_a_safe_message_with_a_reference(): void
    {
        $batch = ImportBatch::factory()->processing()->create();

        (new ProcessExcelImport($batch))->failed(new \RuntimeException('SQLSTATE[HY000] secret DB host 10.0.0.5'));

        $batch->refresh();
        $this->assertSame(ImportBatchStatus::Failed, $batch->status);
        $this->assertStringContainsString("#{$batch->id}", $batch->error_message);
        $this->assertStringNotContainsString('SQLSTATE', $batch->error_message);
    }

    public function test_a_late_failure_does_not_overwrite_a_cancelled_import(): void
    {
        $batch = ImportBatch::factory()->create(['status' => ImportBatchStatus::Cancelled]);

        (new ProcessExcelImport($batch))->failed(new \RuntimeException('boom'));

        $this->assertSame(ImportBatchStatus::Cancelled, $batch->refresh()->status);
    }
}
