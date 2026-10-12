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
        Schema::table('seo_project_competitors', function (Blueprint $table) {
            // Optional override for when the sitemap isn't at the common
            // default path - see App\Domain\Seo\CompetitorSitemapResolver,
            // which tries a short list of common paths and robots.txt
            // before giving up when this is left blank.
            $table->string('sitemap_url')->nullable()->after('domain');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('seo_project_competitors', function (Blueprint $table) {
            $table->dropColumn('sitemap_url');
        });
    }
};
