<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('seo_wp_sites', function (Blueprint $table) {
            $table->json('health')->nullable();
            $table->timestamp('health_reported_at')->nullable();
        });

        Schema::create('seo_wp_not_found_urls', function (Blueprint $table) {
            $table->id();
            $table->foreignId('seo_wp_site_id')->constrained('seo_wp_sites')->cascadeOnDelete();
            $table->string('path', 255);
            $table->string('path_hash', 40);
            $table->unsignedInteger('hits')->default(0);
            $table->timestamp('reported_at')->nullable();
            $table->timestamps();

            $table->unique(['seo_wp_site_id', 'path_hash']);
        });

        Schema::create('seo_wp_search_terms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('seo_wp_site_id')->constrained('seo_wp_sites')->cascadeOnDelete();
            $table->string('term', 120);
            $table->string('term_hash', 40);
            $table->unsignedInteger('count')->default(0);
            $table->unsignedInteger('misses')->default(0);
            $table->timestamp('reported_at')->nullable();
            $table->timestamps();

            $table->unique(['seo_wp_site_id', 'term_hash']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('seo_wp_search_terms');
        Schema::dropIfExists('seo_wp_not_found_urls');

        Schema::table('seo_wp_sites', function (Blueprint $table) {
            $table->dropColumn(['health', 'health_reported_at']);
        });
    }
};
