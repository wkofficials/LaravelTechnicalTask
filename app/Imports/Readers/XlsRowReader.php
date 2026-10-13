<?php

namespace App\Imports\Readers;

use Generator;
use PhpOffice\PhpSpreadsheet\Reader\Xls;
use Throwable;

/**
 * Legacy binary .xls (Excel 97-2003). The format can't be streamed, so the
 * first sheet is loaded once (values only); it holds at most 65,536 rows,
 * which keeps memory bounded.
 */
class XlsRowReader implements RowReader
{
    public function __construct(private readonly string $path)
    {
    }

    public function rows(): Generator
    {
        try {
            $reader = new Xls();
            $reader->setReadDataOnly(true);
            $reader->setLoadSheetsOnly([$reader->listWorksheetNames($this->path)[0] ?? '']);
            $sheet = $reader->load($this->path)->getSheet(0);
        } catch (Throwable $e) {
            throw new UnreadableSpreadsheet('The .xls file could not be read.', previous: $e);
        }

        $lastColumn = $sheet->getHighestDataColumn();

        foreach ($sheet->getRowIterator() as $row) {
            $rowNumber = $row->getRowIndex();
            $cells = $sheet->rangeToArray("A{$rowNumber}:{$lastColumn}{$rowNumber}", '', true, false)[0];

            yield $rowNumber => array_map(fn ($value) => (string) $value, $cells);
        }

        $sheet->getParent()?->disconnectWorksheets();
    }
}
