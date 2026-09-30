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
        Schema::create('seo_search_type_metrics', function (Blueprint $table) {
            $table->id();
            $table->foreignId('seo_project_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            // GSC calls this a "type" request parameter (web/image/video/
            // news/discover/googleNews), not a dimension - "web" is never
            // stored here, it's already SeoDailyMetric's whole table.
            $table->string('search_type', 20);
            $table->unsignedInteger('clicks')->default(0);
            $table->unsignedInteger('impressions')->default(0);
            $table->decimal('ctr', 8, 4)->default(0);
            $table->decimal('position', 8, 2)->default(0);
            $table->timestamps();

            $table->unique(['seo_project_id', 'date', 'search_type'], 'seo_search_type_metrics_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('seo_search_type_metrics');
    }
};
