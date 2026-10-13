<?php

namespace App\Console\Commands;

use App\Models\DataExport;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * Deletes export files once they expire (scheduled hourly in routes/console.php).
 * The export row is kept, marked expired, as a record that it happened.
 */
class PruneExpiredExports extends Command
{
    protected $signature = 'exports:prune';

    protected $description = 'Delete expired export files';

    public function handle(): int
    {
        $pruned = 0;

        DataExport::withoutGlobalScopes()
            ->where('status', DataExport::STATUS_COMPLETED)
            ->where('expires_at', '<=', now())
            ->lazyById(500)
            ->each(function (DataExport $export) use (&$pruned) {
                if ($export->file_path) {
                    Storage::disk('local')->delete($export->file_path);
                }

                $export->forceFill(['status' => DataExport::STATUS_EXPIRED, 'file_path' => null])->save();
                $pruned++;
            });

        $this->info("Pruned {$pruned} expired export(s).");

        return self::SUCCESS;
    }
}
