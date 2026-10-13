<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Manual (invoice + bank transfer) billing. No card data is ever stored;
     * a payment provider can be added later behind the same tables.
     */
    public function up(): void
    {
        Schema::table('plans', function (Blueprint $table) {
            // Prices are set by a platform admin; null = not sold at a fixed price.
            $table->unsignedBigInteger('price_monthly_cents')->nullable()->after('max_templates');
            $table->unsignedBigInteger('price_yearly_cents')->nullable()->after('price_monthly_cents');
            $table->char('currency', 3)->default('USD')->after('price_yearly_cents');
            // Listed on the public pricing page.
            $table->boolean('is_public')->default(true)->after('is_default');
        });

        Schema::create('subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('plan_id')->constrained();
            $table->string('status', 20); // trialing, active, past_due, cancelled
            $table->string('billing_interval', 10)->nullable(); // monthly, yearly
            $table->timestamp('trial_ends_at')->nullable();
            $table->timestamp('current_period_start')->nullable();
            $table->timestamp('current_period_end')->nullable();
            $table->boolean('cancel_at_period_end')->default(false);
            $table->timestamp('ended_at')->nullable();
            // For a future payment provider (customer / subscription ids).
            $table->string('provider', 30)->default('manual');
            $table->string('provider_reference')->nullable();
            $table->timestamps();

            $table->index(['status', 'current_period_end']);
        });

        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('plan_id')->constrained();
            $table->string('number', 30)->nullable()->unique();
            $table->string('status', 10); // open, paid, void
            $table->string('billing_interval', 10);
            $table->char('currency', 3);
            $table->unsignedBigInteger('amount_cents');
            $table->string('description');
            $table->timestamp('period_start');
            $table->timestamp('period_end');
            $table->timestamp('due_at');
            $table->timestamp('paid_at')->nullable();
            $table->string('payment_reference')->nullable(); // e.g. bank transfer reference
            $table->text('notes')->nullable();
            $table->foreignId('issued_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['company_id', 'status']);
            $table->index(['status', 'due_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('invoices');
        Schema::dropIfExists('subscriptions');

        Schema::table('plans', function (Blueprint $table) {
            $table->dropColumn(['price_monthly_cents', 'price_yearly_cents', 'currency', 'is_public']);
        });
    }
};
