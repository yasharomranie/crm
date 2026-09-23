<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One row per order that used a discount code - lets per-customer
     * limits (section 27-29) be enforced by counting rows instead of
     * needing a separate counter per customer. The code's own used_count
     * column (incremented atomically - see DiscountService) is the fast
     * path for the global usage_limit check.
     */
    public function up(): void
    {
        Schema::create('discount_usages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('discount_code_id')->constrained()->cascadeOnDelete();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->string('customer_phone')->nullable();
            $table->unsignedBigInteger('discount_amount');
            $table->timestamps();

            $table->index(['discount_code_id', 'customer_phone']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('discount_usages');
    }
};
