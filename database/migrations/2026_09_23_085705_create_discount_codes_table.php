<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('discount_codes', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('discount_type');
            $table->unsignedBigInteger('discount_value');
            $table->dateTime('start_date')->nullable();
            $table->dateTime('end_date')->nullable();
            $table->unsignedInteger('usage_limit')->nullable();
            $table->unsignedInteger('used_count')->default(0);
            $table->unsignedInteger('per_customer_usage_limit')->nullable();
            $table->unsignedBigInteger('minimum_purchase_amount')->nullable();
            $table->unsignedBigInteger('maximum_discount_amount')->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        // orders.discount_code_id was created unconstrained (this table
        // didn't exist yet at that point in the migration order) - wire
        // the real FK up now that it does.
        Schema::table('orders', function (Blueprint $table) {
            $table->foreign('discount_code_id')->references('id')->on('discount_codes')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropForeign(['discount_code_id']);
        });

        Schema::dropIfExists('discount_codes');
    }
};
