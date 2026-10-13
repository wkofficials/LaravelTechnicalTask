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
        Schema::table('import_batches', function (Blueprint $table) {
            // Fixed when processing starts, so retries split the file the same way.
            $table->unsignedInteger('chunk_size')->nullable()->after('duplicate_policy');
            // Set when the original upload is deleted by the retention job.
            $table->timestamp('file_deleted_at')->nullable()->after('file_path');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('import_batches', function (Blueprint $table) {
            $table->dropColumn(['chunk_size', 'file_deleted_at']);
        });
    }
};
