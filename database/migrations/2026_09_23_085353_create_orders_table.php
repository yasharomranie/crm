<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('order_number')->unique();
            $table->string('source_type');
            // POS/online reconciliation (spec section 117-118) - the
            // caller's own reference for this order, distinct from our
            // order_number.
            $table->string('external_order_id')->nullable()->index();
            // Wired to external_api_clients once that table exists (created
            // later in this same feature) - unconstrained here purely to
            // avoid a migration-ordering dependency.
            $table->unsignedBigInteger('external_api_client_id')->nullable();
            $table->string('customer_name')->nullable();
            $table->string('customer_phone')->nullable();
            // Wired to discount_codes once that table exists - unconstrained
            // here for the same migration-ordering reason as above.
            $table->unsignedBigInteger('discount_code_id')->nullable();
            $table->string('discount_code_snapshot')->nullable();
            $table->string('discount_type_snapshot')->nullable();
            $table->unsignedBigInteger('discount_value_snapshot')->nullable();
            // Every money column below is a permanent snapshot at order
            // time (section 19) - never recomputed from live prices.
            $table->unsignedBigInteger('original_amount')->default(0);
            $table->unsignedBigInteger('discount_amount')->default(0);
            $table->unsignedBigInteger('tax_amount')->default(0);
            $table->unsignedBigInteger('final_amount')->default(0);
            $table->unsignedBigInteger('amount_paid')->default(0);
            $table->string('currency', 3)->default('IRR');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('source_type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
