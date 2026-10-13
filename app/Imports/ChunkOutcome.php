<?php

namespace App\Imports;

/**
 * What one chunk of an import will write, collected before the chunk's
 * transaction so everything commits together.
 */
final class ChunkOutcome
{
    /** @var array<int, array<string, ?string>> */
    public array $inserts = [];

    /** @var array<int, array<string, ?string>>  Existing record id => new values. */
    public array $updates = [];

    /** @var array<int, array{row_number: int, code: string, message: string, row_data: array<string, ?string>}> */
    public array $errors = [];

    /** @var array<string, int>  Unique key => row number, for keys written in this chunk. */
    public array $acceptedKeys = [];

    public int $skipped = 0;

    /**
     * @param  array<string, ?string>  $values
     */
    public function insert(int $rowNumber, string $key, array $values): void
    {
        $this->inserts[] = $values;
        $this->acceptedKeys[$key] = $rowNumber;
    }

    /**
     * @param  array<string, ?string>  $values
     */
    public function update(int $rowNumber, string $key, int $id, array $values): void
    {
        $this->updates[$id] = $values;
        $this->acceptedKeys[$key] = $rowNumber;
    }

    /**
     * @param  array<string, ?string>  $rowData
     */
    public function reject(int $rowNumber, string $code, string $message, array $rowData): void
    {
        $this->errors[] = ['row_number' => $rowNumber, 'code' => $code, 'message' => $message, 'row_data' => $rowData];
    }

    public function skip(): void
    {
        $this->skipped++;
    }

    public function acceptedRowFor(string $key): ?int
    {
        return $this->acceptedKeys[$key] ?? null;
    }
}
