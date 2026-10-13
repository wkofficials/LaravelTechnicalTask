<?php

namespace App\Jobs;

use App\Enums\ImportBatchStatus;
use App\Imports\Readers\UnreadableSpreadsheet;
use App\Imports\SpreadsheetInspector;
use App\Models\ImportBatch;
use App\Support\PlanLimits;
use App\Support\Tenancy\CurrentCompany;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * First step after upload: read the heading row, a few sample rows and the
 * row count, then wait for the user to confirm the column mapping.
 * Runs on the queue so the upload request never parses the file.
 */
class AnalyzeImport implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public const PREVIEW_ROWS = 5;

    public int $timeout = 600;

    /**
     * The import/export (or its whole company) was deleted while queued:
     * drop the job quietly instead of failing it.
     */
    public bool $deleteWhenMissingModels = true;

    public int $tries = 3;

    /**
     * @var array<int, int>
     */
    public array $backoff = [10, 60];

    public function __construct(public readonly ImportBatch $importBatch)
    {
    }

    public function handle(CurrentCompany $currentCompany, SpreadsheetInspector $inspector): void
    {
        $currentCompany->runAs($this->importBatch->company, fn () => $this->analyze($inspector));
    }

    private function analyze(SpreadsheetInspector $inspector): void
    {
        $batch = $this->importBatch->refresh();

        if ($batch->status !== ImportBatchStatus::Analyzing) {
            return; // cancelled meanwhile, or already analyzed by an earlier attempt
        }

        $disk = Storage::disk('local');

        if (! $disk->exists($batch->file_path)) {
            $batch->finishWith(ImportBatchStatus::Failed, 'The uploaded file could not be found. Please upload it again.');

            return;
        }

        try {
            $preview = $inspector->inspect($disk->path($batch->file_path), self::PREVIEW_ROWS);
        } catch (UnreadableSpreadsheet $e) {
            Log::warning("Import #{$batch->id}: unreadable file", ['exception' => $e]);
            $batch->finishWith(ImportBatchStatus::Failed, 'The file could not be read. Make sure it is a valid, unprotected .xlsx, .xls or .csv file.');
            $this->fail($e);

            return;
        }

        $totalRows = $preview['total'];

        $problem = match (true) {
            $preview['headers'] === [] => 'The first row of the file must contain column headings.',
            count($preview['headers']) > SpreadsheetInspector::MAX_COLUMNS => 'The file has more than '.SpreadsheetInspector::MAX_COLUMNS.' columns.',
            $preview['rows'] === [] => 'The file has a heading row but no data rows.',
            default => PlanLimits::for($batch->company)->checkRowsPerImport($totalRows),
        };

        if ($problem !== null) {
            $batch->forceFill(['total_rows' => $totalRows])->save();
            $batch->finishWith(ImportBatchStatus::Failed, $problem);

            return;
        }

        ImportBatch::whereKey($batch->id)
            ->where('status', ImportBatchStatus::Analyzing->value)
            ->update([
                'headers' => json_encode($preview['headers']),
                'preview_rows' => json_encode($preview['rows']),
                'total_rows' => $totalRows,
                'status' => ImportBatchStatus::AwaitingConfirmation->value,
            ]);
    }

    public function failed(Throwable $exception): void
    {
        $this->importBatch->finishWith(
            ImportBatchStatus::Failed,
            "The file could not be analyzed due to a system error. Please contact support and quote import #{$this->importBatch->id}."
        );
    }
}
