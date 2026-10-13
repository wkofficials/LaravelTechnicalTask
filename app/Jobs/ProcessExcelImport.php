<?php

namespace App\Jobs;

use App\Enums\ImportBatchStatus;
use App\Exceptions\ImportCancelled;
use App\Imports\DatasetImport;
use App\Imports\Readers\RowReaderFactory;
use App\Imports\Readers\UnreadableSpreadsheet;
use App\Imports\SpreadsheetInspector;
use App\Models\ImportBatch;
use App\Models\ImportRowKey;
use App\Notifications\ImportFinishedNotification;
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
 * Writes a confirmed import into its dataset (see DatasetImport).
 */
class ProcessExcelImport implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Generous ceiling for a ~100k row file; must stay below DB_QUEUE_RETRY_AFTER
     * so the queue doesn't consider this job "lost" and hand it to another worker
     * while it's still legitimately running.
     */
    public int $timeout = 3600;

    /**
     * The import/export (or its whole company) was deleted while queued:
     * drop the job quietly instead of failing it.
     */
    public bool $deleteWhenMissingModels = true;

    /**
     * Retrying is safe: every chunk is committed atomically together with a
     * marker, and chunks already committed are skipped (see DatasetImport).
     */
    public int $tries = 3;

    /**
     * Seconds to wait before the 2nd and 3rd attempt (e.g. DB briefly down).
     *
     * @var array<int, int>
     */
    public array $backoff = [30, 120];

    public function __construct(public readonly ImportBatch $importBatch)
    {
    }

    public function handle(CurrentCompany $currentCompany, SpreadsheetInspector $inspector, RowReaderFactory $readers): void
    {
        // Workers have no session: the tenant comes from the batch itself,
        // so every query and insert below is confined to the batch's company.
        $currentCompany->runAs($this->importBatch->company, fn () => $this->process($inspector, $readers));
    }

    private function process(SpreadsheetInspector $inspector, RowReaderFactory $readers): void
    {
        $batch = $this->importBatch->refresh();

        // Only confirmed imports run; finished ones (e.g. cancelled while
        // queued, or completed by an earlier attempt) are left alone.
        if (! in_array($batch->status, [ImportBatchStatus::Pending, ImportBatchStatus::Processing], true)) {
            return;
        }

        if ($batch->cancel_requested_at !== null) {
            $this->markCancelled();

            return;
        }

        if (empty($batch->mapping)) {
            $this->markFailed('The import has no confirmed column mapping.');

            return;
        }

        $disk = Storage::disk('local');

        if (! $batch->file_path || ! $disk->exists($batch->file_path)) {
            $this->markFailed('The uploaded file could not be found. Please upload it again.');

            return;
        }

        $path = $disk->path($batch->file_path);

        try {
            $batch->forceFill([
                'status' => ImportBatchStatus::Processing,
                'started_at' => $batch->started_at ?? now(),
                'chunk_size' => $batch->chunk_size ?? max(1, (int) config('imports.chunk_size')),
                'total_rows' => $batch->total_rows ?? $inspector->countDataRows($path),
            ])->save();

            (new DatasetImport($batch, $batch->dataset()))->run($readers->for($path));
        } catch (ImportCancelled) {
            $this->markCancelled();

            return;
        } catch (UnreadableSpreadsheet $e) {
            // The file itself is unreadable; retrying can't fix that.
            Log::warning("Import #{$batch->id}: unreadable file", ['exception' => $e]);
            $this->markFailed('The file could not be read. Make sure it is a valid, unprotected .xlsx, .xls or .csv file.');
            $this->fail($e);

            return;
        }

        $batch->refresh();

        $batch->forceFill([
            'status' => $batch->failed_rows > 0
                ? ImportBatchStatus::CompletedWithErrors
                : ImportBatchStatus::Completed,
            'finished_at' => now(),
        ])->save();

        // Duplicate-tracking keys are only needed while the import runs.
        ImportRowKey::where('import_batch_id', $batch->id)->delete();

        $this->notifyUploader();
        PlanLimits::for($batch->company)->warnIfNearMonthlyLimit();
    }

    /**
     * Called once all attempts are used up. The technical details stay in the
     * logs / failed_jobs table; the user gets a safe message and a reference.
     */
    public function failed(Throwable $exception): void
    {
        $this->markFailed("The import could not be completed due to a system error. Please contact support and quote import #{$this->importBatch->id}.");
    }

    private function markFailed(string $message): void
    {
        // Only notify if this actually ended the import (not if it had
        // already been cancelled or failed).
        if ($this->importBatch->finishWith(ImportBatchStatus::Failed, $message)) {
            $this->notifyUploader();
        }
    }

    /**
     * A notification problem (e.g. mail server down) must never turn a
     * finished import into a failed job, so it is reported, not thrown.
     */
    private function notifyUploader(): void
    {
        $batch = $this->importBatch->refresh();

        // Someone removed from the company meanwhile must not hear about its data.
        if (! $batch->user || ! $batch->user->belongsToCompany($batch->company)) {
            return;
        }

        try {
            $batch->user->notify(new ImportFinishedNotification($batch));
        } catch (Throwable $e) {
            report($e);
        }
    }

    private function markCancelled(): void
    {
        $batch = $this->importBatch->refresh();

        $batch->finishWith(ImportBatchStatus::Cancelled, $batch->importedRows() > 0
            ? "Cancelled. {$batch->importedRows()} row(s) imported before cancelling were kept."
            : 'Cancelled before any rows were imported.');
    }
}
