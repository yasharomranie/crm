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
        Schema::create('seo_competitor_sitemap_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('seo_project_competitor_id')->constrained('seo_project_competitors', 'id', 'scse_competitor_fk')->cascadeOnDelete();
            $table->text('url');
            $table->string('url_hash', 40);
            $table->timestamp('lastmod')->nullable();
            // sitemap.xml never carries a title - filled in once, when the
            // URL is first discovered, the same way App\Domain\Seo\
            // SitemapMonitorService does it for the project's own sitemap
            // (see App\Domain\Seo\HtmlPageTitleFetcher).
            $table->string('title')->nullable();
            // Which of my own topic clusters this competitor page was
            // matched to - null until App\Domain\Seo\CompetitorTopicMatcher
            // runs, and still null afterwards for a page it couldn't
            // confidently classify (e.g. a login page, a category index).
            $table->foreignId('topic_cluster_id')->nullable()->constrained('seo_topic_clusters', 'id', 'scse_topic_cluster_fk')->nullOnDelete();
            // Same convention as SeoKeywordProfile::classified_at - lets
            // "reviewed, no confident match" be told apart from "not yet
            // reviewed" even though topic_cluster_id is null either way,
            // so CompetitorTopicMatcher never re-sends the same page to
            // the AI on every run.
            $table->timestamp('classified_at')->nullable();
            $table->timestamp('first_seen_at');
            $table->timestamp('last_seen_at');
            $table->timestamp('last_changed_at');
            $table->timestamps();

            $table->unique(['seo_project_competitor_id', 'url_hash'], 'scse_url_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('seo_competitor_sitemap_entries');
    }
};
