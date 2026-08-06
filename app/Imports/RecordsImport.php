<?php

namespace App\Imports;

use App\Models\ImportBatch;
use App\Models\Record;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class RecordsImport implements ToCollection, WithHeadingRow, WithChunkReading
{
    private const INSERT_CHUNK = 500;

    private const MAX_STORED_ERRORS = 100;

    private int $rowsSeenBeforeThisChunk = 0;

    public function __construct(private readonly ImportBatch $importBatch)
    {
    }

    /**
     * Called once per chunk read from the file (see WithChunkReading::chunkSize()).
     * Validates, bulk-inserts valid rows, and records progress on the batch.
     */
    public function collection(Collection $rows): void
    {
        $now = now();
        $toInsert = [];
        $newErrors = [];
        $failedInThisChunk = 0;

        foreach ($rows as $index => $row) {
            $name = trim((string) ($row['name'] ?? ''));
            $email = trim((string) ($row['email'] ?? ''));

            if ($name === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $failedInThisChunk++;

                if ($this->importBatch->failed_rows + $failedInThisChunk <= self::MAX_STORED_ERRORS) {
                    $newErrors[] = [
                        'row' => $this->rowsSeenBeforeThisChunk + $index + 2, // +2: 1-indexed + heading row
                        'error' => $name === '' ? 'Name is required.' : 'Invalid or missing email.',
                    ];
                }

                continue;
            }

            $toInsert[] = [
                'import_batch_id' => $this->importBatch->id,
                'name' => $name,
                'email' => $email,
                'phone' => $this->nullableString($row['phone'] ?? null),
                'company' => $this->nullableString($row['company'] ?? null),
                'city' => $this->nullableString($row['city'] ?? null),
                'country' => $this->nullableString($row['country'] ?? null),
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        foreach (array_chunk($toInsert, self::INSERT_CHUNK) as $insertChunk) {
            Record::insert($insertChunk);
        }

        $this->rowsSeenBeforeThisChunk += $rows->count();

        $this->importBatch->row_errors = array_merge($this->importBatch->row_errors ?? [], $newErrors);
        $this->importBatch->processed_rows += $rows->count();
        $this->importBatch->failed_rows += $failedInThisChunk;
        $this->importBatch->save();
    }

    /**
     * Rows read per chunk from the spreadsheet (memory-safe for ~100k+ row files).
     */
    public function chunkSize(): int
    {
        return 1000;
    }

    private function nullableString(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
