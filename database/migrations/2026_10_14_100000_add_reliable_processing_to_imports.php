<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Every rejected row, instead of the first 100 in a JSON column.
        Schema::create('import_errors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('import_batch_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('row_number');
            $table->string('error_code', 50);
            $table->string('message');
            // Text, not JSON: MySQL's JSON type re-sorts keys, and the error
            // report should keep the spreadsheet's original column order.
            $table->text('row_data')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index(['import_batch_id', 'row_number']);
        });

        // One row per committed chunk. Written in the same transaction as the
        // chunk's records, so a retried job can tell exactly which chunks are
        // already saved and skip them (no duplicate records, no double counts).
        Schema::create('import_chunks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('import_batch_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('start_row');
            $table->unsignedInteger('row_count');
            $table->unsignedInteger('failed_count');
            $table->timestamp('created_at')->nullable();

            $table->unique(['import_batch_id', 'start_row']);
        });

        Schema::table('import_batches', function (Blueprint $table) {
            $table->timestamp('cancel_requested_at')->nullable()->after('error_message');
            $table->dropColumn('row_errors');

            $table->index(['company_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('import_batches', function (Blueprint $table) {
            // MySQL uses the composite index for the company_id foreign key, so
            // a plain one has to exist before the composite can be dropped.
            $table->index('company_id');
            $table->dropIndex(['company_id', 'status']);
            $table->dropColumn('cancel_requested_at');
            $table->json('row_errors')->nullable()->after('failed_rows');
        });

        Schema::dropIfExists('import_chunks');
        Schema::dropIfExists('import_errors');
    }
};
