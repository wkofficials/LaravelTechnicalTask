<?php

namespace App\Jobs;

use App\Enums\ImportBatchStatus;
use App\Imports\RecordsImport;
use App\Models\ImportBatch;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Throwable;

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
     * Bulk inserts already committed by earlier chunks aren't undone on failure,
     * so an automatic retry would re-insert them as duplicates. Fail once, loudly.
     */
    public int $tries = 1;

    public function __construct(public readonly ImportBatch $importBatch)
    {
    }

    public function handle(): void
    {
        $disk = Storage::disk('local');

        if (! $disk->exists($this->importBatch->file_path)) {
            $this->markFailed('Uploaded file is missing from storage.');

            return;
        }

        $this->importBatch->forceFill([
            'status' => ImportBatchStatus::Processing,
            'started_at' => now(),
            'total_rows' => $this->countDataRows($disk->path($this->importBatch->file_path)),
        ])->save();

        try {
            Excel::import(new RecordsImport($this->importBatch), $this->importBatch->file_path, 'local');
        } catch (Throwable $e) {
            $this->markFailed($e->getMessage());

            throw $e;
        }

        $this->importBatch->refresh();

        $this->importBatch->forceFill([
            'status' => $this->importBatch->failed_rows > 0
                ? ImportBatchStatus::CompletedWithErrors
                : ImportBatchStatus::Completed,
            'finished_at' => now(),
        ])->save();
    }

    public function failed(Throwable $exception): void
    {
        $this->markFailed($exception->getMessage());
    }

    private function markFailed(string $message): void
    {
        $this->importBatch->forceFill([
            'status' => ImportBatchStatus::Failed,
            'error_message' => $message,
            'finished_at' => now(),
        ])->save();
    }

    /**
     * Cheap upfront row count so progress can be shown as a percentage.
     * CSV is counted by lines; xlsx/xls use PhpSpreadsheet's sheet-dimension
     * metadata rather than reading every cell.
     */
    private function countDataRows(string $absolutePath): ?int
    {
        try {
            $extension = strtolower(pathinfo($absolutePath, PATHINFO_EXTENSION));

            if ($extension === 'csv') {
                $lines = 0;
                $handle = fopen($absolutePath, 'r');

                while (fgets($handle) !== false) {
                    $lines++;
                }

                fclose($handle);

                return max(0, $lines - 1);
            }

            $info = IOFactory::createReaderForFile($absolutePath)->listWorksheetInfo($absolutePath);

            return max(0, ($info[0]['totalRows'] ?? 1) - 1);
        } catch (Throwable) {
            return null;
        }
    }
}
