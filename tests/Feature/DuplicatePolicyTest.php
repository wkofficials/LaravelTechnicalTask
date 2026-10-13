<?php

namespace Tests\Feature;

use App\Enums\DuplicatePolicy;
use App\Enums\ImportBatchStatus;
use App\Imports\DatasetImport;
use App\Jobs\ProcessExcelImport;
use App\Models\Company;
use App\Models\ImportBatch;
use App\Models\ImportRowKey;
use App\Models\Record;
use App\Support\Tenancy\CurrentCompany;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DuplicatePolicyTest extends TestCase
{
    use RefreshDatabase;

    private Company $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');

        $this->tenant = Company::factory()->create();
        app(CurrentCompany::class)->set($this->tenant);
    }

    /**
     * @param  array<string, int|null>|null  $mapping
     */
    private function import(string $csv, DuplicatePolicy $policy, ?array $mapping = null, ?array $headers = null): ImportBatch
    {
        Storage::disk('local')->put('imports/file.csv', $csv);

        $attributes = ['file_path' => 'imports/file.csv', 'duplicate_policy' => $policy];
        if ($mapping !== null) {
            $attributes['mapping'] = $mapping;
        }
        if ($headers !== null) {
            $attributes['headers'] = $headers;
        }

        $batch = ImportBatch::factory()->create($attributes);

        app()->call([new ProcessExcelImport($batch), 'handle']);

        return $batch->refresh();
    }

    private function existing(string $email, array $attributes = []): Record
    {
        return Record::factory()->create(['email' => $email] + $attributes);
    }

    public function test_reject_reports_rows_matching_existing_records(): void
    {
        $this->existing('jane@example.test', ['name' => 'Original']);

        $batch = $this->import("name,email\nJane New,JANE@example.test\nJohn,john@example.test\n", DuplicatePolicy::Reject);

        $this->assertSame(ImportBatchStatus::CompletedWithErrors, $batch->status);
        $this->assertSame([1, 0, 1], [$batch->inserted_rows, $batch->updated_rows, $batch->failed_rows]);
        $this->assertSame('duplicate_existing', $batch->errors()->sole()->error_code);
        $this->assertSame('Original', Record::where('email', 'jane@example.test')->sole()->name);
    }

    public function test_skip_leaves_existing_records_untouched(): void
    {
        $this->existing('jane@example.test', ['name' => 'Original']);

        $batch = $this->import("name,email\nJane New,jane@example.test\nJohn,john@example.test\n", DuplicatePolicy::Skip);

        $this->assertSame(ImportBatchStatus::Completed, $batch->status);
        $this->assertSame([1, 0, 1, 0], [$batch->inserted_rows, $batch->updated_rows, $batch->skipped_rows, $batch->failed_rows]);
        $this->assertSame('Original', Record::where('email', 'jane@example.test')->sole()->name);
    }

    public function test_update_overwrites_only_non_empty_mapped_values(): void
    {
        $original = $this->existing('jane@example.test', ['name' => 'Jane', 'phone' => '111', 'city' => 'Lahore', 'company' => 'Acme']);

        // phone is empty in the file, company isn't mapped at all.
        $batch = $this->import(
            "name,email,phone,city\nJane Doe,jane@example.test,,Karachi\nJohn,john@example.test,222,Multan\n",
            DuplicatePolicy::Update,
            ['name' => 0, 'email' => 1, 'phone' => 2, 'company' => null, 'city' => 3, 'country' => null],
            ['name', 'email', 'phone', 'city'],
        );

        $this->assertSame([1, 1, 0], [$batch->inserted_rows, $batch->updated_rows, $batch->failed_rows]);

        $jane = $original->fresh();
        $this->assertSame('Jane Doe', $jane->name);
        $this->assertSame('Karachi', $jane->city);
        $this->assertSame('111', $jane->phone, 'empty cell must not clear the value');
        $this->assertSame('Acme', $jane->company, 'unmapped field must be untouched');
        $this->assertSame(1, Record::where('email', 'jane@example.test')->count());
    }

    public function test_duplicates_within_the_file_keep_the_first_row(): void
    {
        $batch = $this->import(
            "name,email\nFirst,dup@example.test\nSecond,DUP@example.test\nOther,other@example.test\n",
            DuplicatePolicy::Update,
        );

        $this->assertSame([2, 0, 1], [$batch->inserted_rows, $batch->updated_rows, $batch->failed_rows]);
        $this->assertSame('First', Record::where('email', 'dup@example.test')->sole()->name);

        $error = $batch->errors()->sole();
        $this->assertSame([3, 'duplicate_in_file'], [$error->row_number, $error->error_code]);
        $this->assertStringContainsString('row 2', $error->message);
    }

    public function test_duplicates_within_the_file_are_caught_across_chunks(): void
    {
        $rows = collect(range(1, DatasetImport::CHUNK_SIZE))->map(fn ($i) => "P{$i},p{$i}@example.test")->implode("\n");

        // The last row repeats the first one, but lands in the second chunk.
        $batch = $this->import("name,email\n{$rows}\nAgain,p1@example.test\n", DuplicatePolicy::Skip);

        $this->assertSame(DatasetImport::CHUNK_SIZE, $batch->inserted_rows);
        $this->assertSame(1, $batch->skipped_rows);
        $this->assertSame('P1', Record::where('email', 'p1@example.test')->sole()->name);
    }

    public function test_row_keys_are_cleaned_up_after_the_import(): void
    {
        $this->import("name,email\nJane,jane@example.test\n", DuplicatePolicy::Reject);

        $this->assertSame(0, ImportRowKey::count());
    }

    public function test_records_of_other_companies_are_not_treated_as_duplicates(): void
    {
        $otherCompany = Company::factory()->create();
        Record::factory()->create(['email' => 'jane@example.test', 'company_id' => $otherCompany->id, 'name' => 'Theirs']);

        $batch = $this->import("name,email\nOurs,jane@example.test\n", DuplicatePolicy::Update);

        $this->assertSame([1, 0], [$batch->inserted_rows, $batch->updated_rows]);
        $this->assertSame('Theirs', Record::withoutGlobalScopes()->where('company_id', $otherCompany->id)->sole()->name);
    }

    public function test_mapping_uses_column_positions_and_ignores_unmapped_columns(): void
    {
        $batch = $this->import(
            "Notes,Mobile,E-mail,Full Name\nignore me,0300,jane@example.test,Jane\n",
            DuplicatePolicy::Reject,
            ['name' => 3, 'email' => 2, 'phone' => 1, 'company' => null, 'city' => null, 'country' => null],
            ['Notes', 'Mobile', 'E-mail', 'Full Name'],
        );

        $this->assertSame(1, $batch->inserted_rows);
        $record = Record::sole();
        $this->assertSame(['Jane', 'jane@example.test', '0300', null], [$record->name, $record->email, $record->phone, $record->company]);
    }

    public function test_blank_rows_are_not_reported_as_errors(): void
    {
        $batch = $this->import("name,email\nJane,jane@example.test\n,\n,\n", DuplicatePolicy::Reject);

        $this->assertSame(ImportBatchStatus::Completed, $batch->status);
        $this->assertSame(0, $batch->failed_rows);
    }

    public function test_rejected_rows_keep_original_values_under_the_files_headers(): void
    {
        $batch = $this->import(
            "Full Name,E-mail\nJane,not-an-email\n",
            DuplicatePolicy::Reject,
            ['name' => 0, 'email' => 1, 'phone' => null, 'company' => null, 'city' => null, 'country' => null],
            ['Full Name', 'E-mail'],
        );

        $this->assertSame(['Full Name' => 'Jane', 'E-mail' => 'not-an-email'], $batch->errors()->sole()->row_data);
    }
}
