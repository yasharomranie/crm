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
        Schema::create('seo_query_metrics', function (Blueprint $table) {
            $table->id();
            $table->foreignId('seo_project_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->string('query');
            // GSC queries have no fixed length limit, so the raw column
            // can't carry a normal unique index (MySQL's key-length cap) -
            // a fixed-length hash of it can. Sync upserts key off the hash;
            // `query` itself stays plain text for display/search.
            $table->string('query_hash', 40);
            $table->unsignedInteger('clicks')->default(0);
            $table->unsignedInteger('impressions')->default(0);
            $table->decimal('ctr', 8, 4)->default(0);
            $table->decimal('position', 8, 2)->default(0);
            $table->timestamps();

            $table->unique(['seo_project_id', 'date', 'query_hash'], 'seo_query_metrics_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('seo_query_metrics');
    }
};
