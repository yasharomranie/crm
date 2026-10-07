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
        Schema::create('trip_plan_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('trip_plan_id')->constrained()->cascadeOnDelete();
            $table->foreignId('attraction_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('day_number');
            $table->time('start_time');
            $table->time('end_time');
            $table->text('note')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('trip_plan_items');
    }
};
