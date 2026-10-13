<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Every list query is "WHERE company_id = ? ORDER BY <column>" (or filters
     * on a column), so the useful indexes start with company_id. The old
     * single-column indexes predate multi-tenancy and are replaced.
     * (email is already covered by the unique (company_id, email) index.)
     */
    private const SORT_AND_FILTER_COLUMNS = ['id', 'name', 'phone', 'company', 'city', 'country', 'created_at'];

    private const OLD_SINGLE_COLUMN_INDEXES = ['email', 'name', 'company', 'phone', 'city', 'country'];

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('records', function (Blueprint $table) {
            foreach (self::SORT_AND_FILTER_COLUMNS as $column) {
                $table->index(['company_id', $column]);
            }
        });

        Schema::table('records', function (Blueprint $table) {
            foreach (self::OLD_SINGLE_COLUMN_INDEXES as $column) {
                $table->dropIndex([$column]);
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('records', function (Blueprint $table) {
            foreach (self::OLD_SINGLE_COLUMN_INDEXES as $column) {
                $table->index($column);
            }
        });

        Schema::table('records', function (Blueprint $table) {
            foreach (self::SORT_AND_FILTER_COLUMNS as $column) {
                $table->dropIndex(['company_id', $column]);
            }
        });
    }
};
