<?php

namespace App\Models;

use App\Enums\AiKnowledgeEntrySource;
use App\Enums\AiKnowledgeEntryStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One fact/note in a custom AiAssistantProfile's knowledge base - typed in
 * directly by its creator, or proposed by the assistant itself mid-
 * conversation (see HasCustomAssistantChatEngine's save_knowledge_entry
 * tool). Only Approved entries are ever fed back into a future chat's
 * system prompt; see AiAssistantProfile::approvedKnowledgeForPrompt().
 */
class AiAssistantKnowledgeEntry extends Model
{
    use HasFactory;

    protected $fillable = ['ai_assistant_profile_id', 'content', 'status', 'source'];

    protected function casts(): array
    {
        return [
            'status' => AiKnowledgeEntryStatus::class,
            'source' => AiKnowledgeEntrySource::class,
        ];
    }

    public function profile(): BelongsTo
    {
        return $this->belongsTo(AiAssistantProfile::class, 'ai_assistant_profile_id');
    }

    public function scopeApproved(Builder $query): Builder
    {
        return $query->where('status', AiKnowledgeEntryStatus::Approved);
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', AiKnowledgeEntryStatus::Pending);
    }
}
