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
        Schema::table('seo_technical_audits', function (Blueprint $table) {
            // From Google PageSpeed Insights, run once per audit against the
            // project's homepage only (not every crawled page - see
            // PageSpeedInsightsClient's own doc comment for why).
            $table->unsignedTinyInteger('performance_score')->nullable()->after('score');
            $table->json('core_web_vitals')->nullable()->after('performance_score');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('seo_technical_audits', function (Blueprint $table) {
            $table->dropColumn(['performance_score', 'core_web_vitals']);
        });
    }
};
