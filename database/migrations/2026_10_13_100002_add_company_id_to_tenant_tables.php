<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tenant-owned tables. Every row in these must belong to exactly one company.
     */
    private const TENANT_TABLES = ['import_batches', 'records'];

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Added nullable, backfilled, then made required; the foreign key comes
        // last because MySQL can't change a column a foreign key depends on.
        foreach (self::TENANT_TABLES as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->unsignedBigInteger('company_id')->nullable()->after('id');
            });
        }

        // The single-company version called the top role "admin"; inside a
        // company workspace that role is now the company "owner".
        DB::table('roles')->where('name', 'admin')->update(['name' => 'owner']);

        $this->moveExistingDataIntoDefaultCompany();

        foreach (self::TENANT_TABLES as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->unsignedBigInteger('company_id')->nullable(false)->change();
                $table->foreign('company_id')->references('id')->on('companies')->cascadeOnDelete();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('roles')->where('name', 'owner')->update(['name' => 'admin']);

        foreach (self::TENANT_TABLES as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->dropConstrainedForeignId('company_id');
            });
        }
    }

    /**
     * Data created before multi-tenancy (users, imports, records) is adopted
     * by a single "Default Company" so nothing is orphaned or lost.
     */
    private function moveExistingDataIntoDefaultCompany(): void
    {
        $hasExistingData = DB::table('users')->exists()
            || DB::table('records')->exists()
            || DB::table('import_batches')->exists();

        if (! $hasExistingData) {
            return;
        }

        $now = now();

        $companyId = DB::table('companies')->insertGetId([
            'name' => 'Default Company',
            'slug' => 'default-company',
            'status' => 'active',
            'timezone' => 'UTC',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        DB::table('users')->orderBy('id')->each(function ($user) use ($companyId, $now) {
            DB::table('company_user')->insert([
                'company_id' => $companyId,
                'user_id' => $user->id,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        });

        DB::table('users')->update(['current_company_id' => $companyId]);
        DB::table('model_has_roles')->update(['company_id' => $companyId]);
        DB::table('model_has_permissions')->update(['company_id' => $companyId]);

        foreach (self::TENANT_TABLES as $tableName) {
            DB::table($tableName)->update(['company_id' => $companyId]);
        }
    }
};
