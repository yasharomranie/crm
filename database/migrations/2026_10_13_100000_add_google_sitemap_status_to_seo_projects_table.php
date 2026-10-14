<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('seo_projects', function (Blueprint $table) {
            $table->json('google_sitemap_status')->nullable()->after('sitemap_url');
            $table->timestamp('google_sitemap_checked_at')->nullable()->after('google_sitemap_status');
        });
    }

    public function down(): void
    {
        Schema::table('seo_projects', function (Blueprint $table) {
            $table->dropColumn(['google_sitemap_status', 'google_sitemap_checked_at']);
        });
    }
};
