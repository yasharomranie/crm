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
        Schema::create('seo_opportunities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('seo_project_id')->constrained()->cascadeOnDelete();
            $table->string('type');
            $table->string('title');
            $table->string('subject_query')->nullable();
            $table->string('subject_page_url')->nullable();
            $table->json('evidence');
            $table->json('recommended_actions');
            $table->string('priority');
            $table->string('confidence');
            $table->string('status')->default('detected');
            $table->dateTime('detected_at');
            $table->dateTime('resolved_at')->nullable();
            $table->timestamps();

            $table->index(['seo_project_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('seo_opportunities');
    }
};
