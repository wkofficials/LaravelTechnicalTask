<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Checked first: MySQL can't roll back schema changes, so fail
        // before anything has been altered.
        $this->assertContactEmailsAreUniquePerCompany();

        // Saved column mappings, private to a company. Mappings are stored by
        // header *name* so they still apply when a file's column order changes.
        Schema::create('import_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('dataset_key', 50);
            $table->string('name');
            $table->text('mapping');
            $table->string('duplicate_policy', 20);
            $table->unsignedInteger('schema_version');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['company_id', 'name']);
        });

        Schema::table('import_batches', function (Blueprint $table) {
            $table->string('dataset_key', 50)->default('contacts')->after('user_id');
            $table->foreignId('template_id')->nullable()->after('dataset_key')
                ->constrained('import_templates')->nullOnDelete();
            // Text rather than JSON: keeps the spreadsheet's column order on MySQL.
            $table->text('headers')->nullable()->after('file_path');
            $table->text('preview_rows')->nullable()->after('headers');
            $table->text('mapping')->nullable()->after('preview_rows');
            $table->string('duplicate_policy', 20)->nullable()->after('mapping');
            $table->unsignedInteger('inserted_rows')->default(0)->after('failed_rows');
            $table->unsignedInteger('updated_rows')->default(0)->after('inserted_rows');
            $table->unsignedInteger('skipped_rows')->default(0)->after('updated_rows');
        });

        // Imports made before this change only ever inserted.
        DB::table('import_batches')->update(['inserted_rows' => DB::raw('processed_rows - failed_rows')]);

        // Unique keys accepted so far in an import, committed with each chunk,
        // so duplicates *within one file* are caught even across chunks and retries.
        Schema::create('import_row_keys', function (Blueprint $table) {
            $table->id();
            $table->foreignId('import_batch_id')->constrained()->cascadeOnDelete();
            $table->char('key_hash', 40);
            $table->unsignedInteger('row_number');

            $table->unique(['import_batch_id', 'key_hash']);
        });

        Schema::table('records', function (Blueprint $table) {
            $table->unique(['company_id', 'email']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('records', function (Blueprint $table) {
            // MySQL backs the company_id foreign key with this index; give it another first.
            $table->index('company_id');
            $table->dropUnique(['company_id', 'email']);
        });

        Schema::dropIfExists('import_row_keys');

        Schema::table('import_batches', function (Blueprint $table) {
            $table->dropConstrainedForeignId('template_id');
            $table->dropColumn([
                'dataset_key', 'headers', 'preview_rows', 'mapping', 'duplicate_policy',
                'inserted_rows', 'updated_rows', 'skipped_rows',
            ]);
        });

        Schema::dropIfExists('import_templates');
    }

    /**
     * Contacts are identified by email within a company. Emails are stored
     * lower-case; existing duplicates must be resolved by hand first rather
     * than this migration silently deleting someone's data.
     */
    private function assertContactEmailsAreUniquePerCompany(): void
    {
        $duplicates = DB::table('records')
            ->selectRaw('company_id, LOWER(email)')
            ->groupByRaw('company_id, LOWER(email)')
            ->havingRaw('COUNT(*) > 1')
            ->get()
            ->count();

        if ($duplicates > 0) {
            throw new RuntimeException(
                "Found {$duplicates} email address(es) used by more than one contact in the same company. "
                .'Merge or delete those duplicate records, then run the migration again.'
            );
        }

        DB::table('records')->update(['email' => DB::raw('LOWER(email)')]);
    }
};
