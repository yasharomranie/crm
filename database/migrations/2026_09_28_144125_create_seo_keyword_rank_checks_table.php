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
        Schema::create('seo_keyword_rank_checks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('seo_tracked_keyword_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->unsignedInteger('position')->nullable();
            // TEXT, not VARCHAR(255) - a real result URL can exceed 255
            // chars, the same lesson as seo_page_metrics.page_url.
            $table->text('found_url')->nullable();
            $table->dateTime('checked_at');
            $table->timestamps();

            $table->unique(['seo_tracked_keyword_id', 'date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('seo_keyword_rank_checks');
    }
};
