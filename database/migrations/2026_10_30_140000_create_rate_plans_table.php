<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rate_plans', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('adjustment_type')->default('percentage');
            $table->decimal('adjustment_value', 10, 2)->default(0);
            $table->foreignId('cancellation_policy_id')->nullable()->constrained('cancellation_policies')->nullOnDelete();
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::table('bookings', function (Blueprint $table) {
            $table->foreignId('rate_plan_id')->nullable()->constrained('rate_plans')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropConstrainedForeignId('rate_plan_id');
        });

        Schema::dropIfExists('rate_plans');
    }
};
