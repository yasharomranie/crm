<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One inter-agent exchange: a custom assistant profile asked another one
 * a specific question mid-conversation (AssistantConsultationService) and
 * got an answer back. Kept purely as an audit trail - approvedKnowledge-
 * ForPrompt() never reads from here, so a consultation never silently
 * becomes "knowledge" on its own; the answering assistant's own response
 * is just woven into the asking assistant's reply by the model itself.
 */
class AiAssistantConsultation extends Model
{
    use HasFactory;

    protected $fillable = [
        'consulting_ai_assistant_profile_id',
        'consulted_ai_assistant_profile_id',
        'ai_conversation_id',
        'question',
        'answer',
    ];

    public function consultingProfile(): BelongsTo
    {
        return $this->belongsTo(AiAssistantProfile::class, 'consulting_ai_assistant_profile_id');
    }

    public function consultedProfile(): BelongsTo
    {
        return $this->belongsTo(AiAssistantProfile::class, 'consulted_ai_assistant_profile_id');
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(AiConversation::class, 'ai_conversation_id');
    }
}
