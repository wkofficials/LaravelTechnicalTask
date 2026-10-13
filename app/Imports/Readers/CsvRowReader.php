<?php

namespace App\Imports\Readers;

use Generator;

/**
 * Streams a CSV file with fgetcsv (quoted fields with line breaks work).
 *
 * - The delimiter is detected from the heading row (",", ";", tab or "|"),
 *   since Excel in many locales saves CSV with ";".
 * - A UTF-8 byte order mark is removed; files that aren't valid UTF-8 are
 *   treated as Windows-1252 (what Excel on Windows writes).
 */
class CsvRowReader implements RowReader
{
    /**
     * Longest line accepted. Longer lines are a malformed or hostile file,
     * not a spreadsheet.
     */
    public const MAX_LINE_BYTES = 1_048_576;

    private const DELIMITERS = [',', ';', "\t", '|'];

    public function __construct(private readonly string $path)
    {
    }

    public function rows(): Generator
    {
        $handle = @fopen($this->path, 'r');

        if ($handle === false) {
            throw new UnreadableSpreadsheet('The file could not be opened.');
        }

        try {
            $firstLine = fgets($handle, self::MAX_LINE_BYTES + 1);

            if ($firstLine === false) {
                return; // empty file
            }

            if (strlen($firstLine) > self::MAX_LINE_BYTES) {
                throw new UnreadableSpreadsheet('The first row is too long to be a heading row.');
            }

            $delimiter = $this->detectDelimiter($firstLine);
            rewind($handle);

            // Skip a UTF-8 byte order mark.
            if (fread($handle, 3) !== "\xEF\xBB\xBF") {
                rewind($handle);
            }

            $rowNumber = 0;

            while (($cells = fgetcsv($handle, self::MAX_LINE_BYTES, $delimiter, '"', '')) !== false) {
                $rowNumber++;

                // fgetcsv returns [null] for an empty line.
                yield $rowNumber => $cells === [null] ? [] : array_map($this->toUtf8(...), $cells);
            }
        } finally {
            fclose($handle);
        }
    }

    private function detectDelimiter(string $line): string
    {
        // Ignore quoted parts so commas inside "Doe, Jane" don't count.
        $unquoted = preg_replace('/"[^"]*"/', '', $line);
        $counts = [];

        foreach (self::DELIMITERS as $delimiter) {
            $counts[$delimiter] = substr_count($unquoted, $delimiter);
        }

        arsort($counts);

        return reset($counts) > 0 ? array_key_first($counts) : ',';
    }

    private function toUtf8(?string $value): string
    {
        $value ??= '';

        return mb_check_encoding($value, 'UTF-8') ? $value : mb_convert_encoding($value, 'UTF-8', 'Windows-1252');
    }
}
