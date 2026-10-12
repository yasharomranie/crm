<?php

namespace App\Models;

use App\Enums\AiKnowledgeEntrySource;
use App\Enums\AiKnowledgeEntryStatus;
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
 * except for the narrow, self-contained tools in HasCustomAssistantChat
 * Engine: save_knowledge_entry and fetch_url (knowledge base), and
 * consult_assistant (AssistantConsultationService), which lets this
 * profile ask another visible, consultable profile a specific question
 * by specialty instead of answering outside its own expertise.
 */
class AiAssistantProfile extends Model
{
    use HasFactory;

    protected $fillable = [
        'created_by', 'name', 'description', 'avatar_shape', 'avatar_color', 'is_shared', 'allowed_tools', 'model',
        'knowledge_auto_save', 'specialties', 'consultable', 'fetch_url_enabled',
    ];

    protected function casts(): array
    {
        return [
            'is_shared' => 'boolean',
            'allowed_tools' => 'array',
            'knowledge_auto_save' => 'boolean',
            'specialties' => 'array',
            'consultable' => 'boolean',
            'fetch_url_enabled' => 'boolean',
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

    public function ocrUploads(): HasMany
    {
        return $this->hasMany(AiAssistantOcrUpload::class)->latest();
    }

    public function watchedSources(): HasMany
    {
        return $this->hasMany(AiAssistantWatchedSource::class)->latest();
    }

    public function scheduledPrompts(): HasMany
    {
        return $this->hasMany(AiAssistantScheduledPrompt::class)->latest();
    }

    public function consultationsAsRequester(): HasMany
    {
        return $this->hasMany(AiAssistantConsultation::class, 'consulting_ai_assistant_profile_id');
    }

    public function consultationsAsConsultant(): HasMany
    {
        return $this->hasMany(AiAssistantConsultation::class, 'consulted_ai_assistant_profile_id');
    }

    /**
     * Records a note the AI itself produced (from a tool call during chat,
     * or from WatchedSourceChecker finding a changed watched page) -
     * Approved immediately if the owner turned knowledge_auto_save on,
     * Pending (awaiting a one-tap review) otherwise. A manually-typed note
     * (HasCustomAssistantChatEngine::addManualKnowledgeEntry) never goes
     * through here - the owner wrote it themselves, so it needs no review.
     */
    public function recordAiSuggestedKnowledge(string $content): AiAssistantKnowledgeEntry
    {
        return $this->knowledgeEntries()->create([
            'content' => $content,
            'source' => AiKnowledgeEntrySource::AiSuggested,
            'status' => $this->knowledge_auto_save ? AiKnowledgeEntryStatus::Approved : AiKnowledgeEntryStatus::Pending,
        ]);
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
