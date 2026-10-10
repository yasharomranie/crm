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
        Schema::create('ai_assistant_knowledge_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ai_assistant_profile_id')->constrained()->cascadeOnDelete();
            $table->longText('content');
            // 'pending' | 'approved' - see App\Enums\AiKnowledgeEntryStatus.
            $table->string('status')->default('pending');
            // 'manual' | 'ai_suggested' - see App\Enums\AiKnowledgeEntrySource.
            $table->string('source')->default('manual');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ai_assistant_knowledge_entries');
    }
};
