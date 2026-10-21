<?php

namespace App\Models;

use App\Enums\AiMissionCardKind;
use App\Enums\AiMissionCardStatus;
use App\Enums\AiMissionConfidence;
use App\Enums\AiMissionRoleType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One entry on a mission's board: a claim, who made it, where it came from
 * and how sure the author is. A critique card points at the card it doubts.
 */
class AiMissionCard extends Model
{
    use HasFactory;

    protected $fillable = [
        'ai_mission_id', 'ai_assistant_profile_id', 'role', 'round', 'kind', 'target_card_id',
        'claim', 'source', 'source_chunk_id', 'confidence', 'unsourced', 'status',
    ];

    protected function casts(): array
    {
        return [
            'role' => AiMissionRoleType::class,
            'kind' => AiMissionCardKind::class,
            'confidence' => AiMissionConfidence::class,
            'unsourced' => 'boolean',
            'status' => AiMissionCardStatus::class,
        ];
    }

    public function mission(): BelongsTo
    {
        return $this->belongsTo(AiMission::class, 'ai_mission_id');
    }

    public function sourceChunk(): BelongsTo
    {
        return $this->belongsTo(AiMissionSourceChunk::class, 'source_chunk_id');
    }

    public function profile(): BelongsTo
    {
        return $this->belongsTo(AiAssistantProfile::class, 'ai_assistant_profile_id');
    }
}
