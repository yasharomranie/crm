<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ai_conversation_id')->constrained('ai_conversations')->cascadeOnDelete();
            $table->string('role'); // user | assistant | tool | system
            $table->longText('content')->nullable();
            // Set on an assistant message that requested tool calls, and
            // echoed back on the tool-result messages that answer them -
            // both are required by the OpenAI-compatible chat schema to
            // keep a tool round-trip correctly threaded.
            $table->json('tool_calls')->nullable();
            $table->string('tool_call_id')->nullable();
            $table->string('tool_name')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_messages');
    }
};
