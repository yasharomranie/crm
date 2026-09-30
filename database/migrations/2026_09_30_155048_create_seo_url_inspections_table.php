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
        Schema::create('seo_url_inspections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('seo_project_id')->constrained()->cascadeOnDelete();
            $table->text('page_url');
            $table->string('page_url_hash', 40);
            // Google's own enums for these four (never free text) - see
            // App\Domain\Seo\UrlInspectionClient and
            // App\Models\SeoUrlInspection::explanationInPersian().
            $table->string('verdict', 30)->nullable();
            $table->string('robots_txt_state', 30)->nullable();
            $table->string('indexing_state', 40)->nullable();
            $table->string('page_fetch_state', 30)->nullable();
            // coverageState IS free text from Google (new values can
            // appear without notice), kept as-is alongside the enums above.
            $table->string('coverage_state')->nullable();
            $table->text('google_canonical')->nullable();
            $table->text('user_canonical')->nullable();
            $table->timestamp('last_crawl_time')->nullable();
            $table->timestamp('inspected_at');
            $table->json('raw_response')->nullable();
            $table->timestamps();

            $table->unique(['seo_project_id', 'page_url_hash'], 'seo_url_inspections_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('seo_url_inspections');
    }
};
