<?php

namespace App\Jobs;

use App\Datasets\RecordQuery;
use App\Models\DataExport;
use App\Notifications\ExportReadyNotification;
use App\Support\Csv;
use App\Support\Tenancy\CurrentCompany;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

/**
 * Writes a dataset's records — with the search/filter/sort the user had on
 * screen — to a private CSV file. Rows are read in batches so 100k+ records
 * don't have to fit in memory. Re-running simply rewrites the file.
 */
class ExportRecords implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private const READ_BATCH = 1000;

    public int $timeout = 1800;

    /**
     * The import/export (or its whole company) was deleted while queued:
     * drop the job quietly instead of failing it.
     */
    public bool $deleteWhenMissingModels = true;

    public int $tries = 2;

    /**
     * @var array<int, int>
     */
    public array $backoff = [60];

    public function __construct(public readonly DataExport $export)
    {
    }

    public function handle(CurrentCompany $currentCompany): void
    {
        $currentCompany->runAs($this->export->company, fn () => $this->write());
    }

    private function write(): void
    {
        $export = $this->export->refresh();

        if ($export->status === DataExport::STATUS_COMPLETED) {
            return;
        }

        $export->forceFill(['status' => DataExport::STATUS_PROCESSING])->save();

        $dataset = $export->dataset();
        $columns = $dataset->exportableFields();
        $query = RecordQuery::fromInput($dataset, $export->options ?? []);

        $disk = Storage::disk('local');
        $path = "exports/{$export->company_id}/".Str::uuid().'.csv';
        $disk->makeDirectory(dirname($path));

        $handle = fopen($disk->path($path), 'w');
        // UTF-8 BOM so Excel shows non-English characters correctly.
        fwrite($handle, "\xEF\xBB\xBF");
        Csv::writeRow($handle, array_map(fn ($column) => $dataset->columnLabel($column), $columns));

        $rows = 0;

        try {
            // Offset paging gets slower with every page (20s+ for 100k rows),
            // so first take the matching ids in list order — one indexed
            // query, ~1MB for 100k ids — then load the rows in batches.
            $ids = $query->build()->toBase()->pluck('id')->all();
            $model = $dataset->modelClass();

            foreach (array_chunk($ids, self::READ_BATCH) as $idBatch) {
                $records = $model::query()->whereIn('id', $idBatch)->get(['id', ...$columns])->keyBy('id');

                foreach ($idBatch as $id) {
                    if ($record = $records->get($id)) { // deleted meanwhile → skipped
                        Csv::writeRow($handle, array_map(fn ($column) => $record->getAttribute($column), $columns));
                        $rows++;
                    }
                }
            }
        } catch (Throwable $e) {
            fclose($handle);
            $disk->delete($path); // never leave a half-written file behind

            throw $e;
        }

        fclose($handle);

        if ($export->file_path && $export->file_path !== $path) {
            $disk->delete($export->file_path); // left over from an earlier attempt
        }

        $export->forceFill([
            'status' => DataExport::STATUS_COMPLETED,
            'file_path' => $path,
            'row_count' => $rows,
            'finished_at' => now(),
            'expires_at' => now()->addHours(DataExport::KEEP_FOR_HOURS),
        ])->save();

        try {
            $export->user?->notify(new ExportReadyNotification($export));
        } catch (Throwable $e) {
            report($e); // the file is ready either way
        }
    }

    public function failed(Throwable $exception): void
    {
        $this->export->forceFill([
            'status' => DataExport::STATUS_FAILED,
            'error_message' => "The export could not be created. Please try again or contact support (export #{$this->export->id}).",
            'finished_at' => now(),
        ])->save();
    }
}
