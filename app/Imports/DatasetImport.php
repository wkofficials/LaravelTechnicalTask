<?php

namespace App\Imports;

use App\Datasets\Dataset;
use App\Enums\DuplicatePolicy;
use App\Exceptions\ImportCancelled;
use App\Models\ImportBatch;
use App\Models\ImportChunk;
use App\Models\ImportError;
use App\Models\ImportRowKey;
use App\Models\UsageRecord;
use App\Support\PlanLimits;
use App\Imports\Readers\RowReader;
use App\Imports\Readers\UnreadableSpreadsheet;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Writes a spreadsheet into a dataset using the column mapping and
 * duplicate policy the user confirmed.
 *
 * Each chunk is all-or-nothing: records, row errors, accepted keys, the
 * batch counters and an import_chunks marker commit in one transaction.
 * On a retry, chunks with a marker are skipped, so nothing is written or
 * counted twice.
 */
class DatasetImport
{
    /**
     * Default rows per chunk (config imports.chunk_size). The size an import
     * actually uses is stored on the batch when it starts, so a retry always
     * splits the file the same way.
     */
    public const CHUNK_SIZE = 1000;

    private const WRITE_CHUNK = 500;

    /**
     * Longest original cell value kept with a rejected row.
     */
    private const MAX_STORED_VALUE_LENGTH = 500;

    /**
     * @var array<string, int>  Field key => column index.
     */
    private readonly array $mapping;

    private readonly DuplicatePolicy $policy;

    /**
     * Usage month the rows are billed to (fixed when the job starts).
     */
    private readonly string $usagePeriod;

    public function __construct(private readonly ImportBatch $importBatch, private readonly Dataset $dataset)
    {
        $this->mapping = array_filter($importBatch->mapping ?? [], fn ($index) => $index !== null);
        $this->policy = $importBatch->duplicate_policy ?? DuplicatePolicy::Reject;
        $this->usagePeriod = PlanLimits::for($importBatch->company)->period();
    }

    public function chunkSize(): int
    {
        return $this->importBatch->chunk_size ?: self::CHUNK_SIZE;
    }

    /**
     * Read the file once, top to bottom, and process it chunk by chunk.
     *
     * A chunk is identified by the sheet row it starts at (2, 2 + size, …),
     * so a retry splits the file the same way and skips the chunks an
     * earlier attempt already committed.
     *
     * @throws UnreadableSpreadsheet
     * @throws ImportCancelled
     */
    public function run(RowReader $reader): void
    {
        $committed = array_flip(
            ImportChunk::where('import_batch_id', $this->importBatch->id)->pluck('start_row')->all()
        );

        $size = $this->chunkSize();
        $chunk = [];
        $startRow = null;

        foreach ($reader->rows() as $rowNumber => $cells) {
            if ($rowNumber === 1) {
                continue; // heading row
            }

            $startRow ??= $rowNumber;
            $chunk[] = $cells;

            if (count($chunk) === $size) {
                $this->processUnlessCommitted($committed, $startRow, $chunk);
                $chunk = [];
                $startRow = null;
            }
        }

        if ($chunk !== []) {
            $this->processUnlessCommitted($committed, $startRow, $chunk);
        }
    }

    /**
     * @param  array<int, int>  $committed  Start rows already committed (as keys).
     * @param  array<int, array<int, string>>  $rows
     */
    private function processUnlessCommitted(array $committed, int $startRow, array $rows): void
    {
        if (! isset($committed[$startRow])) {
            $this->processChunk($startRow, $rows);
        }
    }

    /**
     * @param  array<int, array<int, string>>  $rows  Consecutive sheet rows, the first being $startRow.
     */
    private function processChunk(int $startRow, array $rows): void
    {
        if (ImportBatch::whereKey($this->importBatch->id)->whereNotNull('cancel_requested_at')->exists()) {
            throw new ImportCancelled();
        }

        $outcome = new ChunkOutcome();
        $valid = [];

        foreach ($rows as $index => $row) {
            $rowNumber = $startRow + $index;

            if ($this->isBlank($row)) {
                continue; // stray empty lines are not errors
            }

            [$values, $error] = $this->valuesFor($row);

            if ($error !== null) {
                $outcome->reject($rowNumber, $error[0], $error[1], $this->originalValues($row));

                continue;
            }

            $valid[$rowNumber] = ['values' => $values, 'row' => $row];
        }

        $this->applyDuplicatePolicy($valid, $outcome);

        $this->commit($startRow, count($rows), $outcome);
    }

    /**
     * Sort valid rows into insert / update / skip / reject according to the
     * policy, checking both the dataset and earlier rows of this file.
     *
     * @param  array<int, array{values: array<string, ?string>, row: array<int, mixed>}>  $valid
     */
    private function applyDuplicatePolicy(array $valid, ChunkOutcome $outcome): void
    {
        $uniqueField = $this->dataset->uniqueField();
        $uniqueLabel = Str::lower($this->dataset->field($uniqueField)->label);
        $keys = array_values(array_unique(array_map(fn ($item) => $item['values'][$uniqueField], $valid)));

        $existingIds = $this->existingIdsByKey($keys);
        $earlierRows = $this->rowsAcceptedEarlierInThisFile($keys);

        foreach ($valid as $rowNumber => $item) {
            $key = $item['values'][$uniqueField];
            $firstRow = $earlierRows[$key] ?? $outcome->acceptedRowFor($key);

            if ($firstRow !== null) {
                $this->policy === DuplicatePolicy::Skip
                    ? $outcome->skip()
                    : $outcome->reject($rowNumber, 'duplicate_in_file', "Same {$uniqueLabel} as row {$firstRow} of this file.", $this->originalValues($item['row']));

                continue;
            }

            $existingId = $existingIds[$key] ?? null;

            if ($existingId === null) {
                $outcome->insert($rowNumber, $key, $item['values']);

                continue;
            }

            match ($this->policy) {
                DuplicatePolicy::Reject => $outcome->reject($rowNumber, 'duplicate_existing', "A record with this {$uniqueLabel} already exists.", $this->originalValues($item['row'])),
                DuplicatePolicy::Skip => $outcome->skip(),
                DuplicatePolicy::Update => $outcome->update($rowNumber, $key, $existingId, $item['values']),
            };
        }
    }

    private function commit(int $startRow, int $rowCount, ChunkOutcome $outcome): void
    {
        $model = $this->dataset->modelClass();
        $now = now();
        $base = ['company_id' => $this->importBatch->company_id, 'import_batch_id' => $this->importBatch->id];

        DB::transaction(function () use ($model, $now, $base, $startRow, $rowCount, $outcome) {
            // Claimed first: the unique (batch, start_row) key makes a second
            // worker processing the same chunk fail here and roll back.
            ImportChunk::insert([
                'import_batch_id' => $this->importBatch->id,
                'start_row' => $startRow,
                'row_count' => $rowCount,
                'failed_count' => count($outcome->errors),
                'created_at' => $now,
            ]);

            // Bulk insert skips model events, so company_id is set explicitly.
            $inserts = array_map(fn ($values) => $base + $this->withAllFields($values) + ['created_at' => $now, 'updated_at' => $now], $outcome->inserts);

            foreach (array_chunk($inserts, self::WRITE_CHUNK) as $insertChunk) {
                $model::insert($insertChunk);
            }

            // Only non-empty mapped values overwrite; the batch that created
            // the record stays its import_batch_id.
            foreach ($outcome->updates as $id => $values) {
                $model::withoutGlobalScopes()
                    ->where('company_id', $this->importBatch->company_id)
                    ->whereKey($id)
                    ->update(array_filter($values, fn ($value) => $value !== null) + ['updated_at' => $now]);
            }

            $errors = array_map(fn ($error) => [
                'company_id' => $this->importBatch->company_id,
                'import_batch_id' => $this->importBatch->id,
                'row_number' => $error['row_number'],
                'error_code' => $error['code'],
                'message' => $error['message'],
                'row_data' => json_encode($error['row_data']),
                'created_at' => $now,
            ], $outcome->errors);

            foreach (array_chunk($errors, self::WRITE_CHUNK) as $errorChunk) {
                ImportError::insert($errorChunk);
            }

            $keys = array_map(fn ($key, $rowNumber) => [
                'import_batch_id' => $this->importBatch->id,
                'key_hash' => ImportRowKey::hash($key),
                'row_number' => $rowNumber,
            ], array_keys($outcome->acceptedKeys), $outcome->acceptedKeys);

            foreach (array_chunk($keys, self::WRITE_CHUNK) as $keyChunk) {
                ImportRowKey::insert($keyChunk);
            }

            // Metered usage = rows written. The key is per chunk, so a retry
            // that re-runs nothing also counts nothing.
            $written = count($outcome->inserts) + count($outcome->updates);

            if ($written > 0) {
                UsageRecord::insert([
                    'company_id' => $this->importBatch->company_id,
                    'period' => $this->usagePeriod,
                    'metric' => UsageRecord::METRIC_IMPORT_ROWS,
                    'amount' => $written,
                    'import_batch_id' => $this->importBatch->id,
                    'idempotency_key' => "import-rows:{$this->importBatch->id}:{$startRow}",
                    'created_at' => $now,
                ]);
            }

            ImportBatch::whereKey($this->importBatch->id)->incrementEach([
                'processed_rows' => $rowCount,
                'failed_rows' => count($outcome->errors),
                'inserted_rows' => count($outcome->inserts),
                'updated_rows' => count($outcome->updates),
                'skipped_rows' => $outcome->skipped,
            ]);
        });
    }

    /**
     * Mapped, normalized values of a row, or the first validation error.
     *
     * @param  array<int, mixed>  $row
     * @return array{0: array<string, ?string>, 1: array{0: string, 1: string}|null}
     */
    private function valuesFor(array $row): array
    {
        $values = [];

        foreach ($this->dataset->fields() as $field) {
            if (! array_key_exists($field->key, $this->mapping)) {
                continue;
            }

            $value = $field->normalize($row[$this->mapping[$field->key]] ?? null);

            if ($error = $field->validate($value)) {
                return [[], $error];
            }

            $values[$field->key] = $value;
        }

        return [$values, null];
    }

    /**
     * @param  array<int, string>  $keys
     * @return array<string, int>  Unique key => id of the company's existing record.
     */
    private function existingIdsByKey(array $keys): array
    {
        if ($keys === []) {
            return [];
        }

        $model = $this->dataset->modelClass();
        $uniqueField = $this->dataset->uniqueField();

        return $model::withoutGlobalScopes()
            ->where('company_id', $this->importBatch->company_id)
            ->whereIn($uniqueField, $keys)
            ->pluck('id', $uniqueField)
            ->mapWithKeys(fn ($id, $key) => [Str::lower($key) => $id])
            ->all();
    }

    /**
     * @param  array<int, string>  $keys
     * @return array<string, int>  Unique key => row where an earlier chunk accepted it.
     */
    private function rowsAcceptedEarlierInThisFile(array $keys): array
    {
        if ($keys === []) {
            return [];
        }

        $keysByHash = [];

        foreach ($keys as $key) {
            $keysByHash[ImportRowKey::hash($key)] = $key;
        }

        return ImportRowKey::where('import_batch_id', $this->importBatch->id)
            ->whereIn('key_hash', array_keys($keysByHash))
            ->pluck('row_number', 'key_hash')
            ->mapWithKeys(fn ($rowNumber, $hash) => [$keysByHash[$hash] => $rowNumber])
            ->all();
    }

    /**
     * Unmapped fields are stored as null on new records.
     *
     * @param  array<string, ?string>  $values
     * @return array<string, ?string>
     */
    private function withAllFields(array $values): array
    {
        return array_merge(array_fill_keys($this->dataset->fieldKeys(), null), $values);
    }

    /**
     * @param  array<int, mixed>  $row
     */
    private function isBlank(array $row): bool
    {
        foreach ($row as $value) {
            if (trim((string) $value) !== '') {
                return false;
            }
        }

        return true;
    }

    /**
     * The row as it was in the file, keyed by header, so the error report
     * can be corrected and uploaded again.
     *
     * @param  array<int, mixed>  $row
     * @return array<string, ?string>
     */
    private function originalValues(array $row): array
    {
        $values = [];

        foreach (array_keys($this->importBatch->headers ?? []) as $index) {
            $value = $row[$index] ?? null;
            $values[$this->importBatch->columnLabel($index)] = $value === null
                ? null
                : Str::limit((string) $value, self::MAX_STORED_VALUE_LENGTH);
        }

        return $values;
    }
}
