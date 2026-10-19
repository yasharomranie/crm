<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('room_types', function (Blueprint $table) {
            $table->unsignedTinyInteger('base_occupancy')->nullable();
            $table->unsignedBigInteger('extra_adult_fee')->default(0);
            $table->unsignedBigInteger('extra_child_fee')->default(0);
            $table->unsignedTinyInteger('min_nights')->default(1);
        });

        Schema::table('price_rules', function (Blueprint $table) {
            $table->json('weekdays')->nullable();
            $table->unsignedTinyInteger('min_nights')->nullable();
        });

        Schema::create('stay_discounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('room_type_id')->nullable()->constrained('room_types')->cascadeOnDelete();
            $table->unsignedTinyInteger('min_nights');
            $table->decimal('percent', 5, 2);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('booking_promo_codes', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('name')->nullable();
            $table->string('discount_type')->default('percentage');
            $table->decimal('discount_value', 10, 2);
            $table->date('valid_from')->nullable();
            $table->date('valid_until')->nullable();
            $table->unsignedTinyInteger('min_nights')->nullable();
            $table->unsignedInteger('usage_limit')->nullable();
            $table->unsignedInteger('used_count')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::table('bookings', function (Blueprint $table) {
            $table->foreignId('promo_code_id')->nullable()->constrained('booking_promo_codes')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropConstrainedForeignId('promo_code_id');
        });

        Schema::dropIfExists('booking_promo_codes');
        Schema::dropIfExists('stay_discounts');

        Schema::table('price_rules', function (Blueprint $table) {
            $table->dropColumn(['weekdays', 'min_nights']);
        });

        Schema::table('room_types', function (Blueprint $table) {
            $table->dropColumn(['base_occupancy', 'extra_adult_fee', 'extra_child_fee', 'min_nights']);
        });
    }
};
