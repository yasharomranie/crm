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
        // AI-generated ("Keyword Discovery") suggestions - never rankings
        // pulled from a real search-volume API (this app has none), so
        // every row is explicitly a hypothesis for a human to review, not
        // a fact - see AiKeywordDiscoveryService's own doc comment.
        Schema::create('seo_keyword_suggestions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('seo_project_id')->constrained()->cascadeOnDelete();
            $table->string('keyword');
            $table->string('intent')->nullable();
            $table->foreignId('topic_cluster_id')->nullable()->constrained('seo_topic_clusters')->nullOnDelete();
            $table->string('suggested_content_type')->nullable();
            $table->text('reason')->nullable();
            $table->string('status')->default('new');
            $table->timestamps();

            $table->index(['seo_project_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('seo_keyword_suggestions');
    }
};
