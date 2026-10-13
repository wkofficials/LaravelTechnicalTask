<?php

namespace Tests\Unit;

use App\Imports\Readers\CsvRowReader;
use App\Imports\Readers\RowReaderFactory;
use App\Imports\Readers\UnreadableSpreadsheet;
use App\Imports\Readers\XlsxRowReader;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xls as XlsWriter;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx as XlsxWriter;
use PHPUnit\Framework\TestCase;

class RowReadersTest extends TestCase
{
    /** @var array<int, string> */
    private array $files = [];

    protected function tearDown(): void
    {
        array_map('unlink', array_filter($this->files, 'file_exists'));

        parent::tearDown();
    }

    private function file(string $extension, ?string $contents = null): string
    {
        $path = tempnam(sys_get_temp_dir(), 'rows').'.'.$extension;
        $this->files[] = $path;

        if ($contents !== null) {
            file_put_contents($path, $contents);
        }

        return $path;
    }

    /**
     * @return array<int, array<int, string>>
     */
    private function read(string $path): array
    {
        return iterator_to_array((new RowReaderFactory())->for($path)->rows());
    }

    public function test_csv_rows_keep_spreadsheet_row_numbers_including_blank_lines(): void
    {
        $rows = $this->read($this->file('csv', "name,email\nJane,j@x.test\n\nJohn,jo@x.test\n"));

        $this->assertSame([1, 2, 3, 4], array_keys($rows));
        $this->assertSame([], $rows[3]);
        $this->assertSame(['John', 'jo@x.test'], $rows[4]);
    }

    public function test_csv_handles_quoted_line_breaks_bom_and_semicolons(): void
    {
        $csv = "\xEF\xBB\xBFname;note\n\"Doe; Jane\";\"line one\nline two\"\nJohn;x\n";

        $rows = $this->read($this->file('csv', $csv));

        $this->assertSame(['name', 'note'], $rows[1], 'BOM stripped, ; detected');
        $this->assertSame(['Doe; Jane', "line one\nline two"], $rows[2]);
        $this->assertSame(['John', 'x'], $rows[3]);
    }

    public function test_csv_saved_by_excel_in_windows_1252_is_converted_to_utf8(): void
    {
        $rows = $this->read($this->file('csv', "name\n".mb_convert_encoding('José Müller', 'Windows-1252', 'UTF-8')."\n"));

        $this->assertSame('José Müller', $rows[2][0]);
    }

    public function test_txt_extension_is_read_as_csv(): void
    {
        $this->assertInstanceOf(CsvRowReader::class, (new RowReaderFactory())->for($this->file('txt', "a\n")));
    }

    public function test_xlsx_is_streamed_with_row_numbers_and_values_as_text(): void
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->fromArray([['name', 'phone'], ['Jane', '03001234567']], null, 'A1');
        $sheet->fromArray([['John', 42]], null, 'A4'); // row 3 left empty
        $path = $this->file('xlsx');
        (new XlsxWriter($spreadsheet))->save($path);

        $reader = (new RowReaderFactory())->for($path);
        $rows = iterator_to_array($reader->rows());

        $this->assertInstanceOf(XlsxRowReader::class, $reader);
        $this->assertSame(['name', 'phone'], $rows[1]);
        $this->assertSame(['Jane', '03001234567'], $rows[2]);
        $this->assertSame(['John', '42'], $rows[4]);
        $this->assertSame('', implode('', $rows[3] ?? []));
    }

    public function test_legacy_xls_is_read(): void
    {
        $spreadsheet = new Spreadsheet();
        $spreadsheet->getActiveSheet()->fromArray([['name', 'email'], ['Jane', 'j@x.test']], null, 'A1');
        $path = $this->file('xls');
        (new XlsWriter($spreadsheet))->save($path);

        $rows = $this->read($path);

        $this->assertSame(['Jane', 'j@x.test'], $rows[2]);
    }

    public function test_garbage_files_are_reported_as_unreadable(): void
    {
        $this->expectException(UnreadableSpreadsheet::class);

        $this->read($this->file('xlsx', 'this is not a zip'));
    }

    public function test_unsupported_extensions_are_refused(): void
    {
        $this->expectException(UnreadableSpreadsheet::class);

        (new RowReaderFactory())->for($this->file('exe', 'MZ'));
    }
}
