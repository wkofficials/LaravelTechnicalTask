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
        Schema::table('records', function (Blueprint $table) {
            $table->index('phone');
            $table->index('city');
            $table->index('country');
        });

        // FULLTEXT indexes aren't supported by SQLite (used in the test suite);
        // whereFullText() falls back to a LIKE-based query there automatically.
        if (Schema::getConnection()->getDriverName() === 'mysql') {
            Schema::table('records', function (Blueprint $table) {
                $table->fullText(['name', 'email', 'company', 'city', 'country']);
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() === 'mysql') {
            Schema::table('records', function (Blueprint $table) {
                $table->dropFullText(['name', 'email', 'company', 'city', 'country']);
            });
        }

        Schema::table('records', function (Blueprint $table) {
            $table->dropIndex(['phone']);
            $table->dropIndex(['city']);
            $table->dropIndex(['country']);
        });
    }
};
