<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A standing instruction an assistant profile's owner wants run once a
 * day, unattended, at a fixed time - the general-purpose counterpart to
 * AiAssistantWatchedSource (which only ever diffs one URL): "هر روز صبح
 * مهمان‌های امروز رو چک کن" runs through ScheduledPromptRunner exactly
 * like an ordinary chat turn (same system prompt + approved knowledge),
 * with no tools offered - same zero-tool sandboxing as
 * AssistantConsultationService::consult(), since nothing here is meant to
 * take action, only to produce a note for the owner to read. The result
 * lands in the knowledge base via AiAssistantProfile::
 * recordAiSuggestedKnowledge(), the same review queue every other
 * AI-authored note goes through.
 */
class AiAssistantScheduledPrompt extends Model
{
    use HasFactory;

    protected $fillable = ['ai_assistant_profile_id', 'prompt', 'run_at_time', 'is_active'];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'last_run_at' => 'datetime',
        ];
    }

    public function profile(): BelongsTo
    {
        return $this->belongsTo(AiAssistantProfile::class, 'ai_assistant_profile_id');
    }
}
