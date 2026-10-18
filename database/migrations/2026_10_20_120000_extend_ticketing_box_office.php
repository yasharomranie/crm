<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ticket_variants', function (Blueprint $table) {
            $table->boolean('validity_end_of_day')->default(false)->after('default_validity_days');
            $table->unsignedInteger('daily_capacity')->nullable()->after('validity_end_of_day');
            $table->boolean('requires_customer_info')->default(false)->after('daily_capacity');
        });

        Schema::table('ticket_variant_prices', function (Blueprint $table) {
            $table->string('applies_to', 10)->default('all')->after('currency');
        });

        Schema::table('gates', function (Blueprint $table) {
            $table->string('kind', 10)->default('both')->after('product_id');
        });

        Schema::table('admission_tickets', function (Blueprint $table) {
            $table->string('blocked_reason')->nullable()->after('cancellation_reason');
        });

        Schema::create('cashier_shifts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->decimal('opening_float', 14, 0)->default(0);
            $table->dateTime('opened_at');
            $table->dateTime('closed_at')->nullable();
            $table->decimal('expected_cash', 14, 0)->nullable();
            $table->decimal('counted_cash', 14, 0)->nullable();
            $table->decimal('difference', 14, 0)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'closed_at']);
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->foreignId('cashier_shift_id')->nullable()->after('created_by')->constrained('cashier_shifts')->nullOnDelete();
        });

        Schema::table('webhook_subscriptions', function (Blueprint $table) {
            $table->dateTime('last_failed_at')->nullable()->after('last_triggered_at');
            $table->text('last_error')->nullable()->after('last_failed_at');
            $table->unsignedInteger('consecutive_failures')->default(0)->after('last_error');
        });
    }

    public function down(): void
    {
        Schema::table('webhook_subscriptions', function (Blueprint $table) {
            $table->dropColumn(['last_failed_at', 'last_error', 'consecutive_failures']);
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('cashier_shift_id');
        });

        Schema::dropIfExists('cashier_shifts');

        Schema::table('admission_tickets', function (Blueprint $table) {
            $table->dropColumn('blocked_reason');
        });

        Schema::table('gates', function (Blueprint $table) {
            $table->dropColumn('kind');
        });

        Schema::table('ticket_variant_prices', function (Blueprint $table) {
            $table->dropColumn('applies_to');
        });

        Schema::table('ticket_variants', function (Blueprint $table) {
            $table->dropColumn(['validity_end_of_day', 'daily_capacity', 'requires_customer_info']);
        });
    }
};
