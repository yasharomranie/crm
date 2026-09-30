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
        Schema::create('seo_sitemap_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('seo_project_id')->constrained()->cascadeOnDelete();
            $table->text('url');
            $table->string('url_hash', 40);
            $table->timestamp('lastmod')->nullable();
            // sitemap.xml itself never carries a title - filled in once,
            // when the URL is first discovered, by fetching the page
            // itself (see App\Domain\Seo\SitemapMonitorService::fetchTitle).
            $table->string('title')->nullable();
            $table->timestamp('first_seen_at');
            $table->timestamp('last_seen_at');
            $table->timestamp('last_changed_at');
            $table->timestamps();

            $table->unique(['seo_project_id', 'url_hash'], 'seo_sitemap_entries_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('seo_sitemap_entries');
    }
};
