<?php

namespace App\Console\Commands;

use App\Enums\DuplicatePolicy;
use App\Enums\ImportBatchStatus;
use App\Jobs\AnalyzeImport;
use App\Jobs\ProcessExcelImport;
use App\Models\Company;
use App\Models\ImportBatch;
use App\Models\Plan;
use App\Support\Tenancy\CurrentCompany;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use ZipArchive;

/**
 * Repeatable import benchmark: generates a file, runs analysis and the full
 * import exactly as a queue worker would (same jobs, run in-process), and
 * reports duration, throughput and peak memory. Everything it creates lives
 * in a throw-away company that is deleted afterwards.
 */
class BenchmarkImport extends Command
{
    protected $signature = 'import:benchmark
        {--rows=100000 : Data rows in the generated file}
        {--format=csv : csv or xlsx}
        {--chunk= : Chunk size (default: config imports.chunk_size)}
        {--policy=reject : Duplicate policy (reject, skip, update)}
        {--invalid=1 : Percentage of rows made invalid (bad email)}
        {--keep : Keep the benchmark company and its data}';

    protected $description = 'Benchmark a large import end-to-end and report time and memory';

    public function handle(CurrentCompany $currentCompany): int
    {
        $rows = max(1, (int) $this->option('rows'));
        $format = $this->option('format') === 'xlsx' ? 'xlsx' : 'csv';
        $chunk = (int) ($this->option('chunk') ?: config('imports.chunk_size'));
        $policy = DuplicatePolicy::from($this->option('policy'));
        $invalidEvery = (int) $this->option('invalid') > 0 ? (int) round(100 / (int) $this->option('invalid')) : 0;

        $path = 'benchmarks/'.Str::uuid().'.'.$format;
        Storage::disk('local')->makeDirectory('benchmarks');

        $this->info("Generating {$rows} rows ({$format})…");
        $started = microtime(true);
        $format === 'xlsx'
            ? $this->writeXlsx(Storage::disk('local')->path($path), $rows, $invalidEvery)
            : $this->writeCsv(Storage::disk('local')->path($path), $rows, $invalidEvery);
        $generateSeconds = microtime(true) - $started;
        $fileMb = Storage::disk('local')->size($path) / 1048576;

        $company = null;

        try {
            // Unlimited plan so plan limits don't interfere with the measurement.
            $plan = Plan::where('key', 'benchmark')->first() ?? new Plan();
            $plan->forceFill([
                'key' => 'benchmark', 'name' => 'Benchmark', 'is_public' => false, 'max_users' => 1, 'max_rows_per_import' => PHP_INT_MAX >> 33,
                'max_file_size_mb' => Plan::HARD_MAX_FILE_SIZE_MB, 'monthly_import_rows' => PHP_INT_MAX >> 33, 'max_templates' => 1,
            ])->save();

            $company = Company::createWithUniqueSlug('Benchmark '.now()->format('Y-m-d H:i:s'));
            $company->forceFill(['plan_id' => $plan->id, 'email_import_notifications' => false])->save();

            $batch = $currentCompany->runAs($company, fn () => ImportBatch::create([
                'dataset_key' => 'contacts',
                'original_filename' => "benchmark-{$rows}.{$format}",
                'status' => ImportBatchStatus::Analyzing,
            ]));
            $batch->forceFill(['file_path' => $path])->save();

            $analysis = $this->measure(fn () => app()->call([new AnalyzeImport($batch), 'handle']));
            $batch->refresh();

            if ($batch->status !== ImportBatchStatus::AwaitingConfirmation) {
                throw new RuntimeException("Analysis ended as {$batch->status->value}: {$batch->error_message}");
            }

            $batch->forceFill([
                'mapping' => $batch->dataset()->suggestMapping($batch->headers),
                'duplicate_policy' => $policy,
                'chunk_size' => $chunk,
                'status' => ImportBatchStatus::Pending,
            ])->save();

            $import = $this->measure(fn () => app()->call([new ProcessExcelImport($batch), 'handle']));
            $batch->refresh();

            $this->table(['Measure', 'Value'], [
                ['Rows in file', number_format($rows)],
                ['Format / size', strtoupper($format).' / '.number_format($fileMb, 1).' MB'],
                ['Chunk size', number_format($chunk)],
                ['Duplicate policy', $policy->value],
                ['Final status', $batch->status->label()],
                ['Added / updated / skipped / rejected', implode(' / ', array_map('number_format', [$batch->inserted_rows, $batch->updated_rows, $batch->skipped_rows, $batch->failed_rows]))],
                ['Analysis time', number_format($analysis['seconds'], 1).' s'],
                ['Import time', number_format($import['seconds'], 1).' s'],
                ['Throughput', number_format($rows / max($import['seconds'], 0.001)).' rows/s'],
                ['Peak memory (analysis)', number_format($analysis['peak_mb'], 1).' MB'],
                ['Peak memory (import)', number_format($import['peak_mb'], 1).' MB'],
                ['File generation (not part of import)', number_format($generateSeconds, 1).' s'],
                ['PHP / DB / queue', PHP_VERSION.' / '.DB::connection()->getDriverName().' '.DB::connection()->getPdo()->getAttribute(\PDO::ATTR_SERVER_VERSION).' / '.config('queue.default')],
            ]);
        } finally {
            Storage::disk('local')->delete($path);

            if ($company && ! $this->option('keep')) {
                $company->delete(); // cascades to its imports, records, errors and usage
            }
        }

        return self::SUCCESS;
    }

    /**
     * @return array{seconds: float, peak_mb: float}
     */
    private function measure(callable $callback): array
    {
        gc_collect_cycles();
        memory_reset_peak_usage();
        $started = microtime(true);

        $callback();

        return ['seconds' => microtime(true) - $started, 'peak_mb' => memory_get_peak_usage(true) / 1048576];
    }

    /**
     * @return array<int, string>
     */
    private function row(int $i, int $invalidEvery): array
    {
        $email = $invalidEvery > 0 && $i % $invalidEvery === 0 ? "invalid-{$i}" : "person{$i}@bench.test";

        return ["Person {$i}", $email, '0300'.str_pad((string) $i, 7, '0', STR_PAD_LEFT), 'Company '.($i % 500), ['Lahore', 'Karachi', 'Dubai', 'London'][$i % 4], ['Pakistan', 'Pakistan', 'UAE', 'UK'][$i % 4]];
    }

    private function writeCsv(string $file, int $rows, int $invalidEvery): void
    {
        $handle = fopen($file, 'w');
        fputcsv($handle, ['Name', 'Email', 'Phone', 'Company', 'City', 'Country'], escape: '');

        for ($i = 1; $i <= $rows; $i++) {
            fputcsv($handle, $this->row($i, $invalidEvery), escape: '');
        }

        fclose($handle);
    }

    /**
     * Minimal streaming XLSX writer (inline strings) — PhpSpreadsheet would
     * need several GB of memory to build a 100k-row workbook in memory.
     */
    private function writeXlsx(string $file, int $rows, int $invalidEvery): void
    {
        $sheet = tempnam(sys_get_temp_dir(), 'sheet');
        $handle = fopen($sheet, 'w');
        fwrite($handle, '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>');

        $writeRow = function (int $r, array $cells) use ($handle) {
            $xml = "<row r=\"{$r}\">";
            foreach ($cells as $c => $value) {
                $xml .= '<c r="'.chr(65 + $c).$r.'" t="inlineStr"><is><t>'.htmlspecialchars($value, ENT_XML1).'</t></is></c>';
            }
            fwrite($handle, $xml.'</row>');
        };

        $writeRow(1, ['Name', 'Email', 'Phone', 'Company', 'City', 'Country']);
        for ($i = 1; $i <= $rows; $i++) {
            $writeRow($i + 1, $this->row($i, $invalidEvery));
        }

        fwrite($handle, '</sheetData></worksheet>');
        fclose($handle);

        $zip = new ZipArchive();
        $zip->open($file, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $zip->addFromString('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/></Types>');
        $zip->addFromString('_rels/.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>');
        $zip->addFromString('xl/workbook.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="Sheet1" sheetId="1" r:id="rId1"/></sheets></workbook>');
        $zip->addFromString('xl/_rels/workbook.xml.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/></Relationships>');
        $zip->addFile($sheet, 'xl/worksheets/sheet1.xml');
        $zip->close();

        unlink($sheet);
    }
}
