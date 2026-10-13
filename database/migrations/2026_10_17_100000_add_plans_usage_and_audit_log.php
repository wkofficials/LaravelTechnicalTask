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
        // Limits per plan. Prices/billing deliberately live elsewhere (later).
        Schema::create('plans', function (Blueprint $table) {
            $table->id();
            $table->string('key', 50)->unique();
            $table->string('name');
            $table->unsignedInteger('max_users');
            $table->unsignedInteger('max_rows_per_import');
            $table->unsignedInteger('max_file_size_mb');
            $table->unsignedInteger('monthly_import_rows');
            $table->unsignedInteger('max_templates');
            $table->boolean('is_default')->default(false);
            $table->timestamps();
        });

        $now = now();
        $defaultPlanId = DB::table('plans')->insertGetId([
            'key' => 'free', 'name' => 'Free', 'max_users' => 3, 'max_rows_per_import' => 10_000,
            'max_file_size_mb' => 10, 'monthly_import_rows' => 25_000, 'max_templates' => 3,
            'is_default' => true, 'created_at' => $now, 'updated_at' => $now,
        ]);
        DB::table('plans')->insert([
            [
                'key' => 'standard', 'name' => 'Standard', 'max_users' => 10, 'max_rows_per_import' => 100_000,
                'max_file_size_mb' => 50, 'monthly_import_rows' => 500_000, 'max_templates' => 25,
                'is_default' => false, 'created_at' => $now, 'updated_at' => $now,
            ],
            [
                'key' => 'business', 'name' => 'Business', 'max_users' => 50, 'max_rows_per_import' => 250_000,
                'max_file_size_mb' => 100, 'monthly_import_rows' => 5_000_000, 'max_templates' => 200,
                'is_default' => false, 'created_at' => $now, 'updated_at' => $now,
            ],
        ]);

        Schema::table('companies', function (Blueprint $table) {
            $table->foreignId('plan_id')->nullable()->after('timezone')->constrained()->nullOnDelete();
            $table->boolean('email_import_notifications')->default(true)->after('plan_id');
        });

        DB::table('companies')->update(['plan_id' => $defaultPlanId]);

        // Platform operators. Not a Spatie role: those are scoped per company.
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('is_super_admin')->default(false)->after('password');
        });

        // Usage events. The unique idempotency key means a retried job
        // cannot count the same work twice.
        Schema::create('usage_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('period', 7); // YYYY-MM in the company's timezone
            $table->string('metric', 50);
            $table->unsignedInteger('amount');
            $table->foreignId('import_batch_id')->nullable()->constrained()->nullOnDelete();
            $table->string('idempotency_key')->unique();
            $table->timestamp('created_at')->nullable();

            $table->index(['company_id', 'period', 'metric']);
        });

        // Who did what. Metadata holds safe details only (never passwords,
        // tokens or row contents).
        Schema::create('activity_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action', 100);
            $table->string('target_type', 100)->nullable();
            $table->unsignedBigInteger('target_id')->nullable();
            $table->text('metadata')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index(['company_id', 'id']);
            $table->index(['action', 'id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('activity_logs');
        Schema::dropIfExists('usage_records');

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('is_super_admin');
        });

        Schema::table('companies', function (Blueprint $table) {
            $table->dropConstrainedForeignId('plan_id');
            $table->dropColumn('email_import_notifications');
        });

        Schema::dropIfExists('plans');
    }
};
