<?php

namespace Tests\Feature;

use App\Enums\ImportBatchStatus;
use App\Jobs\ProcessExcelImport;
use App\Models\ImportBatch;
use App\Models\Record;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx as XlsxWriter;
use Tests\TestCase;
use Throwable;

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

        (new ProcessExcelImport($batch))->handle();

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

        (new ProcessExcelImport($batch))->handle();

        $batch->refresh();

        $this->assertSame(ImportBatchStatus::CompletedWithErrors, $batch->status);
        $this->assertSame(3, $batch->total_rows);
        $this->assertSame(3, $batch->processed_rows);
        $this->assertSame(2, $batch->failed_rows);
        $this->assertCount(2, $batch->row_errors);

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

        (new ProcessExcelImport($batch))->handle();

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

        (new ProcessExcelImport($batch))->handle();

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

        try {
            (new ProcessExcelImport($batch))->handle();
            $this->fail('Expected an exception to be thrown for a corrupt file.');
        } catch (Throwable) {
            // Expected: the job rethrows so the queue records the failure too.
        }

        $batch->refresh();

        $this->assertSame(ImportBatchStatus::Failed, $batch->status);
        $this->assertNotNull($batch->error_message);
    }
}
