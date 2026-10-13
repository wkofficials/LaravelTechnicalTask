<?php

namespace App\Imports;

use App\Imports\Readers\RowReaderFactory;
use App\Imports\Readers\UnreadableSpreadsheet;

/**
 * Look at an uploaded file before importing it: the heading row, the first
 * few data rows and the number of data rows — in one streaming pass, so
 * even a 100k-row file only ever has one row in memory.
 */
class SpreadsheetInspector
{
    /**
     * Widest file we accept; protects the mapping screen and the preview.
     */
    public const MAX_COLUMNS = 200;

    public function __construct(private readonly RowReaderFactory $readers)
    {
    }

    /**
     * @return array{headers: array<int, string>, rows: array<int, array<int, string>>, total: int}
     *
     * @throws UnreadableSpreadsheet
     */
    public function inspect(string $absolutePath, int $previewRows): array
    {
        $headers = null;
        $rows = [];
        $total = 0;

        foreach ($this->readers->for($absolutePath)->rows() as $cells) {
            if ($headers === null) {
                $headers = $this->trimTrailingBlanks($cells);

                if (count($headers) > self::MAX_COLUMNS) {
                    break; // reported by the caller; no need to read further
                }

                continue;
            }

            $total++;

            if (count($rows) < $previewRows) {
                $rows[] = array_slice($cells, 0, max(1, count($headers)));
            }
        }

        // Trailing blank lines are not data rows.
        return ['headers' => $headers ?? [], 'rows' => $this->withoutBlankRows($rows), 'total' => $total];
    }

    /**
     * Data rows in the file (excluding the heading row).
     *
     * @throws UnreadableSpreadsheet
     */
    public function countDataRows(string $absolutePath): int
    {
        $count = 0;

        foreach ($this->readers->for($absolutePath)->rows() as $_) {
            $count++;
        }

        return max(0, $count - 1);
    }

    /**
     * @param  array<int, string>  $cells
     * @return array<int, string>
     */
    private function trimTrailingBlanks(array $cells): array
    {
        while ($cells !== [] && trim((string) end($cells)) === '') {
            array_pop($cells);
        }

        return array_values($cells);
    }

    /**
     * @param  array<int, array<int, string>>  $rows
     * @return array<int, array<int, string>>
     */
    private function withoutBlankRows(array $rows): array
    {
        return array_values(array_filter($rows, fn ($row) => trim(implode('', $row)) !== ''));
    }
}
