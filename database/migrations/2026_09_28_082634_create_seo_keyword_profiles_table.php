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
        // One row per unique query per project (not per-day, unlike
        // seo_query_metrics) - the rolled-up profile KeywordIntelligenceService
        // rebuilds from the warehouse, and what OpportunityEngine and every
        // later report reads from instead of re-aggregating raw daily rows.
        Schema::create('seo_keyword_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('seo_project_id')->constrained()->cascadeOnDelete();
            $table->string('query');
            $table->string('query_hash', 40);
            $table->decimal('current_position', 8, 2)->nullable();
            $table->decimal('previous_position', 8, 2)->nullable();
            $table->decimal('best_position', 8, 2)->nullable();
            $table->decimal('worst_position', 8, 2)->nullable();
            $table->unsignedInteger('clicks_28d')->default(0);
            $table->unsignedInteger('impressions_28d')->default(0);
            $table->decimal('ctr_28d', 8, 4)->default(0);
            $table->string('trend')->default('stable');
            $table->string('landing_page')->nullable();
            $table->foreignId('topic_cluster_id')->nullable()->constrained('seo_topic_clusters')->nullOnDelete();
            $table->string('business_value')->nullable();
            $table->decimal('opportunity_score', 8, 2)->nullable();
            $table->string('cannibalization_risk')->default('none');
            $table->dateTime('last_analyzed_at')->nullable();
            $table->timestamps();

            $table->unique(['seo_project_id', 'query_hash'], 'seo_keyword_profiles_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('seo_keyword_profiles');
    }
};
