<?php

namespace App\Support;

class Csv
{
    /**
     * Neutralise spreadsheet formula injection: a cell starting with one of
     * these characters would be evaluated by Excel/Sheets when the CSV is
     * opened, so it is prefixed with a single quote to force plain text.
     */
    private const FORMULA_TRIGGERS = ['=', '+', '-', '@', "\t", "\r"];

    public static function safeCell(mixed $value): string
    {
        $value = (string) $value;

        if ($value !== '' && in_array($value[0], self::FORMULA_TRIGGERS, true)) {
            return "'".$value;
        }

        return $value;
    }

    /**
     * @param  array<int, mixed>  $cells
     * @return array<int, string>
     */
    public static function safeRow(array $cells): array
    {
        return array_map(self::safeCell(...), $cells);
    }

    /**
     * Write one formula-safe CSV line. The escape character is disabled
     * (plain RFC 4180 quoting); leaving it implicit triggers a PHP 8.4
     * deprecation on every call, which made 100k-row files very slow.
     *
     * @param  resource  $handle
     * @param  array<int, mixed>  $cells
     */
    public static function writeRow($handle, array $cells): void
    {
        fputcsv($handle, self::safeRow($cells), escape: '');
    }
}
