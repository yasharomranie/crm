<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('seo_wp_sites', function (Blueprint $table) {
            $table->id();
            $table->foreignId('seo_project_id')->unique()->constrained('seo_projects')->cascadeOnDelete();
            $table->string('pairing_code_hash', 64)->nullable()->index();
            $table->dateTime('pairing_expires_at')->nullable();
            $table->text('secret')->nullable();
            $table->string('site_url')->nullable();
            $table->string('plugin_version', 20)->nullable();
            $table->string('wp_version', 20)->nullable();
            $table->string('php_version', 20)->nullable();
            $table->string('seo_plugin', 20)->nullable();
            $table->boolean('has_woocommerce')->default(false);
            $table->json('scopes')->nullable();
            $table->string('status', 20)->default('pending')->index();
            $table->dateTime('last_seen_at')->nullable();
            $table->dateTime('last_sync_at')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamps();
        });

        Schema::create('seo_wp_posts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('seo_wp_site_id')->constrained('seo_wp_sites')->cascadeOnDelete();
            $table->unsignedBigInteger('wp_id');
            $table->string('post_type', 40);
            $table->string('status', 20);
            $table->text('url');
            $table->char('url_hash', 40)->index();
            $table->text('title');
            $table->string('slug')->nullable();
            $table->string('author_name')->nullable();
            $table->dateTime('published_at')->nullable();
            $table->dateTime('modified_at')->nullable();
            $table->unsignedInteger('word_count')->default(0);
            $table->json('headings')->nullable();
            $table->json('internal_links')->nullable();
            $table->json('external_links')->nullable();
            $table->json('images')->nullable();
            $table->json('terms')->nullable();
            $table->json('seo')->nullable();
            $table->json('commerce')->nullable();
            $table->char('content_hash', 40)->nullable();
            $table->dateTime('synced_at');
            $table->timestamps();

            $table->unique(['seo_wp_site_id', 'wp_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('seo_wp_posts');
        Schema::dropIfExists('seo_wp_sites');
    }
};
