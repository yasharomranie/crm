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
        Schema::create('seo_technical_audit_issues', function (Blueprint $table) {
            $table->id();
            $table->foreignId('seo_technical_audit_id')->constrained()->cascadeOnDelete();
            $table->string('type');
            $table->string('severity');
            // TEXT, not VARCHAR(255) - a crawled URL can exceed 255 chars
            // (see SeoKeywordRankCheck/SeoPageMetric's own page_url columns
            // for the same lesson learned earlier in this warehouse).
            $table->text('url')->nullable();
            $table->text('details')->nullable();
            $table->timestamps();

            $table->index(['seo_technical_audit_id', 'severity']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('seo_technical_audit_issues');
    }
};
