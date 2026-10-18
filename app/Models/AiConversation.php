<?php

namespace App\Models;

use App\Enums\AiMessageRole;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class AiConversation extends Model
{
    use HasFactory;

    protected $fillable = ['user_id', 'ai_assistant_profile_id', 'title', 'pending_actions', 'run_label', 'cancel_requested'];

    /**
     * Actions the assistant wants to run that change data and are waiting
     * for the user's explicit confirmation (see HasAiAssistantEngine).
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['pending_actions' => 'array', 'cancel_requested' => 'boolean'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function profile(): BelongsTo
    {
        return $this->belongsTo(AiAssistantProfile::class, 'ai_assistant_profile_id');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(AiMessage::class)->orderBy('created_at');
    }

    /**
     * A short label for the conversation list, derived from the first
     * message the user actually sent (tool/system messages don't count).
     */
    public function deriveTitle(): string
    {
        $firstUserMessage = $this->messages()
            ->where('role', AiMessageRole::User->value)
            ->orderBy('created_at')
            ->value('content');

        return $firstUserMessage
            ? Str::limit($firstUserMessage, 40)
            : 'گفتگوی جدید';
    }
}
