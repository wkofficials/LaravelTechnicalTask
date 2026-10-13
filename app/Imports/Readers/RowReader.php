<?php

namespace App\Imports\Readers;

use Generator;

/**
 * Reads a spreadsheet's first sheet from top to bottom exactly once,
 * keeping only one row in memory at a time.
 *
 * Row numbers are the spreadsheet's own (1 = heading row) and blank rows
 * are yielded too, so numbers in error reports match what the user sees
 * in Excel.
 */
interface RowReader
{
    /**
     * @return Generator<int, array<int, string>>  Row number => cell values as strings.
     *
     * @throws UnreadableSpreadsheet
     */
    public function rows(): Generator;
}
