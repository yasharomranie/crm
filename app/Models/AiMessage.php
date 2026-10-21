<?php

namespace App\Models;

use App\Enums\AiMessageRole;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiMessage extends Model
{
    use HasFactory;

    protected $fillable = [
        'ai_conversation_id', 'ai_assistant_profile_id', 'role', 'content', 'tool_calls', 'tool_call_id', 'tool_name', 'chart', 'feedback',
        'prompt_tokens', 'completion_tokens', 'latency_ms',
    ];

    protected function casts(): array
    {
        return [
            'role' => AiMessageRole::class,
            'tool_calls' => 'array',
            'chart' => 'array',
        ];
    }

    /**
     * The custom assistant that wrote this message - several can take turns in
     * one conversation once a chat has been handed over (handoff_to_assistant).
     */
    public function author(): BelongsTo
    {
        return $this->belongsTo(AiAssistantProfile::class, 'ai_assistant_profile_id');
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(AiConversation::class, 'ai_conversation_id');
    }

    /**
     * The shape the OpenAI-compatible chat schema expects for this message
     * when it's replayed back as conversation history.
     *
     * @return array<string, mixed>
     */
    public function toChatPayload(): array
    {
        $payload = [
            'role' => $this->role->value,
            'content' => $this->content,
        ];

        if ($this->tool_calls) {
            $payload['tool_calls'] = $this->tool_calls;
        }

        if ($this->tool_call_id) {
            $payload['tool_call_id'] = $this->tool_call_id;
        }

        if ($this->tool_name) {
            $payload['name'] = $this->tool_name;
        }

        return $payload;
    }
}
