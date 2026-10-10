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
        Schema::create('ai_assistant_model_settings', function (Blueprint $table) {
            $table->id();
            // e.g. 'ai-assistant', 'contract-assistant' - the built-in,
            // single-instance assistants that (unlike AiAssistantProfile
            // rows) have no DB record of their own to hold this on.
            $table->string('assistant_key')->unique();
            $table->string('model')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ai_assistant_model_settings');
    }
};
