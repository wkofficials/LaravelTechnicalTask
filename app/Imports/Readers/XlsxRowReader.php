<?php

namespace App\Imports\Readers;

use DateInterval;
use DateTimeInterface;
use Generator;
use OpenSpout\Reader\XLSX\Options;
use OpenSpout\Reader\XLSX\Reader;
use Throwable;

/**
 * Streams the first sheet of an .xlsx with OpenSpout: one pass, constant
 * memory, regardless of the number of rows. (PhpSpreadsheet re-parses the
 * whole sheet for every chunk, which is quadratic for large files.)
 *
 * Create through RowReaderFactory, which rejects zip bombs first.
 */
class XlsxRowReader implements RowReader
{
    public function __construct(private readonly string $path)
    {
    }

    public function rows(): Generator
    {
        $options = new Options();
        $options->SHOULD_PRESERVE_EMPTY_ROWS = true; // keep row numbers equal to Excel's
        $options->SHOULD_FORMAT_DATES = true;        // dates as shown in Excel, not serial numbers

        $reader = new Reader($options);

        try {
            $reader->open($this->path);
        } catch (Throwable $e) {
            throw new UnreadableSpreadsheet('The .xlsx file could not be opened.', previous: $e);
        }

        try {
            foreach ($reader->getSheetIterator() as $sheet) {
                foreach ($sheet->getRowIterator() as $rowNumber => $row) {
                    yield $rowNumber => array_map($this->stringify(...), $row->toArray());
                }

                break; // first sheet only
            }
        } catch (UnreadableSpreadsheet $e) {
            throw $e;
        } catch (Throwable $e) {
            throw new UnreadableSpreadsheet('The .xlsx file could not be read.', previous: $e);
        } finally {
            $reader->close();
        }
    }

    private function stringify(mixed $value): string
    {
        return match (true) {
            $value === null => '',
            is_bool($value) => $value ? 'TRUE' : 'FALSE',
            $value instanceof DateTimeInterface => $value->format('Y-m-d H:i:s'),
            $value instanceof DateInterval => $value->format('%H:%I:%S'),
            default => (string) $value,
        };
    }
}
