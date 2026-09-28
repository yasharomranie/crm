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
        Schema::create('seo_opportunity_outcomes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('seo_opportunity_id')->unique()->constrained()->cascadeOnDelete();
            $table->dateTime('baseline_captured_at');
            $table->unsignedInteger('baseline_clicks')->default(0);
            $table->unsignedInteger('baseline_impressions')->default(0);
            $table->decimal('baseline_position', 8, 2)->nullable();
            $table->dateTime('followup_measured_at')->nullable();
            $table->unsignedInteger('followup_clicks')->nullable();
            $table->unsignedInteger('followup_impressions')->nullable();
            $table->decimal('followup_position', 8, 2)->nullable();
            $table->string('verdict')->default('pending');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('seo_opportunity_outcomes');
    }
};
