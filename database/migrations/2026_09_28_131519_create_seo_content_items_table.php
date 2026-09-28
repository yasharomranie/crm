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
        Schema::create('seo_content_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('seo_project_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->string('target_keyword')->nullable();
            $table->foreignId('keyword_profile_id')->nullable()->constrained('seo_keyword_profiles')->nullOnDelete();
            $table->foreignId('keyword_suggestion_id')->nullable()->constrained('seo_keyword_suggestions')->nullOnDelete();
            $table->foreignId('seo_opportunity_id')->nullable()->constrained('seo_opportunities')->nullOnDelete();
            $table->string('content_type')->nullable();
            $table->string('status')->default('idea');
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->date('due_date')->nullable();
            $table->text('published_url')->nullable();
            $table->dateTime('published_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['seo_project_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('seo_content_items');
    }
};
