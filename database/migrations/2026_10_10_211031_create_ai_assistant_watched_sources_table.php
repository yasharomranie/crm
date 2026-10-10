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
        Schema::create('ai_assistant_watched_sources', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ai_assistant_profile_id')->constrained()->cascadeOnDelete();
            $table->string('url');
            $table->string('label')->nullable();
            $table->string('last_content_hash')->nullable();
            $table->timestamp('last_checked_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ai_assistant_watched_sources');
    }
};
