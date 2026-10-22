<?php

namespace App\Models;

use App\Enums\AiMissionActionStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A change the team proposes to make (for example creating a task). It does
 * nothing until the mission's owner approves it; approval runs it once.
 */
class AiMissionAction extends Model
{
    use HasFactory;

    protected $fillable = [
        'ai_mission_id', 'ai_assistant_profile_id', 'round', 'tool', 'arguments', 'summary', 'reason', 'compliance',
        'status', 'decided_by', 'decided_at', 'result', 'error',
    ];

    protected function casts(): array
    {
        return [
            'status' => AiMissionActionStatus::class,
            'arguments' => 'array',
            'result' => 'array',
            'compliance' => 'array',
            'decided_at' => 'datetime',
        ];
    }

    public function mission(): BelongsTo
    {
        return $this->belongsTo(AiMission::class, 'ai_mission_id');
    }

    public function profile(): BelongsTo
    {
        return $this->belongsTo(AiAssistantProfile::class, 'ai_assistant_profile_id');
    }
}
