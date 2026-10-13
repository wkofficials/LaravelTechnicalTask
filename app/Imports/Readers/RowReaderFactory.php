<?php

namespace App\Imports\Readers;

use ZipArchive;

/**
 * Picks the streaming reader for an uploaded file, after cheap safety checks
 * that run before any parsing.
 */
class RowReaderFactory
{
    /**
     * An .xlsx is a zip. Real spreadsheets expand roughly 5-20x; a "zip bomb"
     * expands thousands of times and would exhaust worker memory/disk.
     */
    public const MAX_XLSX_EXPANSION_RATIO = 100;

    public const MAX_XLSX_UNCOMPRESSED_BYTES = 2 * 1024 * 1024 * 1024;

    /**
     * @throws UnreadableSpreadsheet
     */
    public function for(string $absolutePath): RowReader
    {
        return match (strtolower(pathinfo($absolutePath, PATHINFO_EXTENSION))) {
            'csv', 'txt' => new CsvRowReader($absolutePath),
            'xlsx' => $this->xlsx($absolutePath),
            'xls' => new XlsRowReader($absolutePath),
            default => throw new UnreadableSpreadsheet('Unsupported file type.'),
        };
    }

    private function xlsx(string $path): XlsxRowReader
    {
        $zip = new ZipArchive();

        if ($zip->open($path, ZipArchive::RDONLY) !== true) {
            throw new UnreadableSpreadsheet('The .xlsx file is not a valid workbook.');
        }

        $uncompressed = 0;

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $uncompressed += $zip->statIndex($i)['size'] ?? 0;
        }

        $zip->close();

        $limit = min(self::MAX_XLSX_UNCOMPRESSED_BYTES, max(1, filesize($path)) * self::MAX_XLSX_EXPANSION_RATIO);

        if ($uncompressed > $limit) {
            throw new UnreadableSpreadsheet('The .xlsx file expands to an implausible size.');
        }

        return new XlsxRowReader($path);
    }
}
