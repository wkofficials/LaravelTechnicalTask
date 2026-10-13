<?php

namespace App\Console\Commands;

use App\Enums\ImportBatchStatus;
use App\Models\ImportBatch;
use App\Models\ImportRowKey;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * Data retention for imports (scheduled daily in routes/console.php):
 * - deletes original uploads of imports finished more than
 *   imports.file_retention_days ago (history and error reports stay);
 * - cancels imports left unconfirmed for imports.abandon_after_days;
 * - removes duplicate-tracking keys left behind by failed imports.
 */
class PruneImports extends Command
{
    protected $signature = 'imports:prune';

    protected $description = 'Delete old import files and cancel abandoned imports';

    public function handle(): int
    {
        $finished = array_map(fn ($status) => $status->value, ImportBatchStatus::finished());
        $notConfirmed = [ImportBatchStatus::Analyzing->value, ImportBatchStatus::AwaitingConfirmation->value];
        $abandonDays = (int) config('imports.abandon_after_days');

        $abandoned = 0;
        ImportBatch::withoutGlobalScopes()
            ->whereIn('status', $notConfirmed)
            ->where('created_at', '<', now()->subDays($abandonDays))
            ->lazyById(500)
            ->each(function (ImportBatch $batch) use ($abandonDays, &$abandoned) {
                if ($batch->finishWith(ImportBatchStatus::Cancelled, "Cancelled automatically: the column mapping was not confirmed within {$abandonDays} days.")) {
                    $this->deleteFile($batch);
                    $abandoned++;
                }
            });

        $filesDeleted = 0;
        ImportBatch::withoutGlobalScopes()
            ->whereIn('status', $finished)
            ->whereNotNull('file_path')
            ->where('finished_at', '<', now()->subDays((int) config('imports.file_retention_days')))
            ->lazyById(500)
            ->each(function (ImportBatch $batch) use (&$filesDeleted) {
                $this->deleteFile($batch);
                $filesDeleted++;
            });

        $keys = ImportRowKey::whereIn(
            'import_batch_id',
            ImportBatch::withoutGlobalScopes()->select('id')->whereIn('status', $finished)
        )->delete();

        $this->info("Cancelled {$abandoned} abandoned import(s), deleted {$filesDeleted} old file(s), removed {$keys} leftover key(s).");

        return self::SUCCESS;
    }

    private function deleteFile(ImportBatch $batch): void
    {
        if ($batch->file_path) {
            Storage::disk('local')->delete($batch->file_path);
        }

        $batch->forceFill(['file_path' => null, 'file_deleted_at' => now()])->save();
    }
}
