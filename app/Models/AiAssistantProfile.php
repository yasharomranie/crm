<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A user-created custom AI assistant: a saved system prompt plus a "blob
 * mascot" avatar (shape + color), shown as a card in the AI Assistants
 * Hub (app/Filament/Pages/AiAssistantsHub.php). Unlike the built-in
 * management/legal assistants (HasAiAssistantEngine), a profile runs no
 * general-purpose tools - allowed_tools is reserved for a future phase -
 * except for the one narrow, self-contained save_knowledge_entry tool
 * (see HasCustomAssistantChatEngine) that lets it grow its own knowledge
 * base through ordinary use instead of only through manual notes.
 */
class AiAssistantProfile extends Model
{
    use HasFactory;

    protected $fillable = [
        'created_by', 'name', 'description', 'avatar_shape', 'avatar_color', 'is_shared', 'allowed_tools', 'model',
        'knowledge_auto_save',
    ];

    protected function casts(): array
    {
        return [
            'is_shared' => 'boolean',
            'allowed_tools' => 'array',
            'knowledge_auto_save' => 'boolean',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function conversations(): HasMany
    {
        return $this->hasMany(AiConversation::class);
    }

    public function knowledgeEntries(): HasMany
    {
        return $this->hasMany(AiAssistantKnowledgeEntry::class)->latest();
    }

    /**
     * The approved knowledge, newest first, capped at a fixed count so a
     * long-lived assistant's prompt (and the provider's per-request bill)
     * never grows unbounded - once this cap is reached, the oldest
     * approved notes simply stop being included, which for a knowledge
     * base built from ordinary conversation is an acceptable trade-off
     * for Phase 1.
     */
    public function approvedKnowledgeForPrompt(): array
    {
        return $this->knowledgeEntries()
            ->approved()
            ->limit(100)
            ->pluck('content')
            ->all();
    }

    /**
     * Profiles a given user is allowed to see/chat with: their own, plus
     * anyone else's shared (public) ones.
     */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        return $query->where(function (Builder $query) use ($user) {
            $query->where('created_by', $user->id)->orWhere('is_shared', true);
        });
    }

    public function displayName(): string
    {
        return $this->name !== null && trim($this->name) !== '' ? $this->name : 'دستیار بدون‌نام';
    }
}
