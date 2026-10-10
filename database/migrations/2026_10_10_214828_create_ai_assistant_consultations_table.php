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
        Schema::create('ai_assistant_consultations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('consulting_ai_assistant_profile_id')->constrained('ai_assistant_profiles')->cascadeOnDelete();
            $table->foreignId('consulted_ai_assistant_profile_id')->constrained('ai_assistant_profiles')->cascadeOnDelete();
            $table->foreignId('ai_conversation_id')->nullable()->constrained('ai_conversations')->nullOnDelete();
            $table->text('question');
            $table->text('answer')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ai_assistant_consultations');
    }
};
