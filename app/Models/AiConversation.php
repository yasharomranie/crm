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

    protected $fillable = ['user_id', 'title'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
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
