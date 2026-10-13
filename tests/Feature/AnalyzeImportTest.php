<?php

namespace Tests\Feature;

use App\Enums\ImportBatchStatus;
use App\Jobs\AnalyzeImport;
use App\Models\ImportBatch;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AnalyzeImportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
    }

    private function analyze(string $csv): ImportBatch
    {
        Storage::disk('local')->put('imports/file.csv', $csv);

        $batch = ImportBatch::factory()->create([
            'status' => ImportBatchStatus::Analyzing,
            'file_path' => 'imports/file.csv',
            'headers' => null,
            'mapping' => null,
            'duplicate_policy' => null,
        ]);

        app()->call([new AnalyzeImport($batch), 'handle']);

        return $batch->refresh();
    }

    public function test_reads_headers_preview_and_row_count_then_waits_for_confirmation(): void
    {
        $rows = collect(range(1, 8))->map(fn ($i) => "Person {$i},p{$i}@example.test,")->implode("\n");

        $batch = $this->analyze("Full Name,E-mail,Notes\n{$rows}\n");

        $this->assertSame(ImportBatchStatus::AwaitingConfirmation, $batch->status);
        $this->assertSame(['Full Name', 'E-mail', 'Notes'], $batch->headers);
        $this->assertCount(AnalyzeImport::PREVIEW_ROWS, $batch->preview_rows);
        $this->assertSame(['Person 1', 'p1@example.test', ''], $batch->preview_rows[0]);
        $this->assertSame(8, $batch->total_rows);
        $this->assertNull($batch->mapping);
    }

    public function test_trailing_blank_headers_are_dropped(): void
    {
        $batch = $this->analyze("name,email,,\nJane,jane@example.test,,\n");

        $this->assertSame(['name', 'email'], $batch->headers);
    }

    public function test_file_without_data_rows_fails_with_a_clear_message(): void
    {
        $batch = $this->analyze("name,email\n");

        $this->assertSame(ImportBatchStatus::Failed, $batch->status);
        $this->assertStringContainsString('no data rows', $batch->error_message);
    }

    public function test_empty_file_fails_with_a_clear_message(): void
    {
        $batch = $this->analyze('');

        $this->assertSame(ImportBatchStatus::Failed, $batch->status);
        $this->assertStringContainsString('column headings', $batch->error_message);
    }

    public function test_cancelled_import_is_not_analyzed(): void
    {
        Storage::disk('local')->put('imports/file.csv', "name,email\nJane,jane@example.test\n");
        $batch = ImportBatch::factory()->create([
            'status' => ImportBatchStatus::Cancelled,
            'file_path' => 'imports/file.csv',
            'headers' => null,
        ]);

        app()->call([new AnalyzeImport($batch), 'handle']);

        $batch->refresh();
        $this->assertSame(ImportBatchStatus::Cancelled, $batch->status);
        $this->assertNull($batch->headers);
    }
}
