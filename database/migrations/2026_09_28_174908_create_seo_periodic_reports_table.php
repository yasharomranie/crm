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
        Schema::create('seo_periodic_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('seo_project_id')->constrained()->cascadeOnDelete();
            $table->date('period_start');
            $table->date('period_end');
            // The rendered print-ready HTML (same house convention as
            // resources/views/reports/print.blade.php - no PDF library, the
            // viewer opens this in a new tab and uses their browser's own
            // print-to-PDF), stored once at generation time so a report
            // always shows the numbers as they were that period, not
            // whatever the warehouse looks like today.
            $table->longText('html');
            $table->dateTime('generated_at');
            $table->timestamps();

            $table->index(['seo_project_id', 'period_start']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('seo_periodic_reports');
    }
};
