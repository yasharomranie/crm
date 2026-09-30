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
        Schema::table('seo_projects', function (Blueprint $table) {
            $table->string('sitemap_url')->nullable()->after('website_url');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('seo_projects', function (Blueprint $table) {
            $table->dropColumn('sitemap_url');
        });
    }
};
