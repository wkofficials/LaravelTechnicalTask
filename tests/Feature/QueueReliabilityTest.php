<?php

namespace Tests\Feature;

use App\Enums\ImportBatchStatus;
use App\Jobs\AnalyzeImport;
use App\Jobs\ExportRecords;
use App\Jobs\ProcessExcelImport;
use App\Models\ImportBatch;
use App\Models\ImportChunk;
use App\Models\ImportRowKey;
use App\Models\Record;
use App\Models\UsageRecord;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use ReflectionClass;
use RuntimeException;
use Tests\TestCase;

/**
 * What happens when things go wrong mid-import: real exceptions inside a
 * chunk's transaction, retries, configuration mistakes, and retention.
 */
class QueueReliabilityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
    }

    private function fileWithRows(int $rows): string
    {
        $lines = collect(range(1, $rows))->map(fn ($i) => "P{$i},p{$i}@example.test")->implode("\n");
        Storage::disk('local')->put('imports/big.csv', "name,email\n{$lines}\n");

        return 'imports/big.csv';
    }

    /**
     * Make the database fail at the very end of the Nth chunk's transaction —
     * after its records, errors and keys were written — so the test proves
     * all of them roll back together. (usage_records is written once per
     * chunk, last.)
     */
    private function failDuringChunk(int $chunkNumber): void
    {
        $seen = 0;
        $failed = false;

        DB::listen(function ($query) use ($chunkNumber, &$seen, &$failed) {
            if (! $failed && str_contains($query->sql, 'insert into "usage_records"') && ++$seen === $chunkNumber) {
                $failed = true;
                throw new RuntimeException('Simulated database outage');
            }
        });
    }

    public function test_queue_retry_after_is_longer_than_every_job_timeout(): void
    {
        $retryAfter = config('queue.connections.database.retry_after');

        foreach ([AnalyzeImport::class, ProcessExcelImport::class, ExportRecords::class] as $job) {
            $timeout = (new ReflectionClass($job))->getProperty('timeout')->getDefaultValue();

            $this->assertGreaterThan($timeout, $retryAfter, "{$job} could be handed to a second worker while still running.");
        }
    }

    public function test_a_failure_inside_a_chunk_rolls_that_chunk_back_completely(): void
    {
        $batch = ImportBatch::factory()->create(['file_path' => $this->fileWithRows(2500), 'chunk_size' => 1000]);
        $this->failDuringChunk(2);

        try {
            app()->call([new ProcessExcelImport($batch), 'handle']);
            $this->fail('The simulated outage should have surfaced so the queue retries the job.');
        } catch (RuntimeException $e) {
            $this->assertSame('Simulated database outage', $e->getMessage());
        }

        $batch->refresh();
        // Exactly chunk 1 is committed — nothing half-written from chunk 2.
        $this->assertSame(ImportBatchStatus::Processing, $batch->status);
        $this->assertSame(1000, $batch->processed_rows);
        $this->assertSame(1000, Record::where('import_batch_id', $batch->id)->count());
        $this->assertSame(1, ImportChunk::where('import_batch_id', $batch->id)->count());
        $this->assertSame(1000, (int) UsageRecord::where('import_batch_id', $batch->id)->sum('amount'));
    }

    public function test_the_retry_after_a_mid_import_failure_finishes_with_exact_totals(): void
    {
        $batch = ImportBatch::factory()->create(['file_path' => $this->fileWithRows(2500), 'chunk_size' => 1000]);
        $this->failDuringChunk(2);

        try {
            app()->call([new ProcessExcelImport($batch), 'handle']);
        } catch (RuntimeException) {
            // first attempt dies
        }

        // Someone changes the configured chunk size before the retry runs.
        config(['imports.chunk_size' => 300]);

        app()->call([new ProcessExcelImport($batch), 'handle']);

        $batch->refresh();
        $this->assertSame(ImportBatchStatus::Completed, $batch->status);
        $this->assertSame(1000, $batch->chunk_size, 'the retry keeps the original chunk size');
        $this->assertSame([2500, 2500, 0], [$batch->processed_rows, $batch->inserted_rows, $batch->failed_rows]);
        $this->assertSame(2500, Record::where('import_batch_id', $batch->id)->count());
        $this->assertSame(2500, Record::where('import_batch_id', $batch->id)->distinct()->count('email'));
        $this->assertSame(2500, (int) UsageRecord::where('import_batch_id', $batch->id)->sum('amount'));
        $this->assertSame(0, ImportRowKey::where('import_batch_id', $batch->id)->count());
    }

    public function test_jobs_for_deleted_imports_are_dropped_not_failed(): void
    {
        foreach ([AnalyzeImport::class, ProcessExcelImport::class, ExportRecords::class] as $job) {
            $this->assertTrue((new ReflectionClass($job))->getProperty('deleteWhenMissingModels')->getDefaultValue(), $job);
        }
    }

    public function test_prune_deletes_old_files_but_keeps_history_and_recent_files(): void
    {
        Storage::disk('local')->put('imports/old.csv', 'x');
        Storage::disk('local')->put('imports/new.csv', 'x');
        $old = ImportBatch::factory()->completed()->create(['file_path' => 'imports/old.csv', 'finished_at' => now()->subDays(31)]);
        $new = ImportBatch::factory()->completed()->create(['file_path' => 'imports/new.csv', 'finished_at' => now()->subDays(2)]);

        $this->artisan('imports:prune')->assertSuccessful();

        Storage::disk('local')->assertMissing('imports/old.csv');
        Storage::disk('local')->assertExists('imports/new.csv');
        $this->assertNull($old->fresh()->file_path);
        $this->assertNotNull($old->fresh()->file_deleted_at);
        $this->assertSame(ImportBatchStatus::Completed, $old->fresh()->status);
        $this->assertSame('imports/new.csv', $new->fresh()->file_path);
    }

    public function test_prune_cancels_imports_never_confirmed(): void
    {
        Storage::disk('local')->put('imports/stale.csv', 'x');
        $stale = ImportBatch::factory()->awaitingConfirmation()->create(['file_path' => 'imports/stale.csv', 'created_at' => now()->subDays(8)]);
        $fresh = ImportBatch::factory()->awaitingConfirmation()->create(['created_at' => now()->subDay()]);

        $this->artisan('imports:prune')->assertSuccessful();

        $this->assertSame(ImportBatchStatus::Cancelled, $stale->fresh()->status);
        $this->assertStringContainsString('not confirmed within 7 days', $stale->fresh()->error_message);
        Storage::disk('local')->assertMissing('imports/stale.csv');
        $this->assertSame(ImportBatchStatus::AwaitingConfirmation, $fresh->fresh()->status);
    }

    public function test_prune_removes_row_keys_left_by_failed_imports(): void
    {
        $failed = ImportBatch::factory()->create(['status' => ImportBatchStatus::Failed]);
        $running = ImportBatch::factory()->processing()->create();
        foreach ([$failed, $running] as $batch) {
            ImportRowKey::insert(['import_batch_id' => $batch->id, 'key_hash' => sha1('a'), 'row_number' => 2]);
        }

        $this->artisan('imports:prune')->assertSuccessful();

        $this->assertSame([$running->id], ImportRowKey::pluck('import_batch_id')->all());
    }
}
