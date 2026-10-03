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
        Schema::create('seo_internal_links', function (Blueprint $table) {
            $table->id();
            $table->foreignId('seo_technical_audit_id')->constrained()->cascadeOnDelete();
            // TEXT, not VARCHAR(255) - same lesson as seo_technical_audit_issues.url.
            $table->text('from_url');
            $table->text('to_url');
            $table->timestamps();

            $table->index('seo_technical_audit_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('seo_internal_links');
    }
};
