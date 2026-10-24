<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A decision the manager pinned from a team answer. Pins are the team's
 * shared memory: a short list of them is handed to the members of every later
 * conversation of the mission, so they build on what was decided.
 */
class AiMissionPin extends Model
{
    use HasFactory;

    public const MAX_PER_MISSION = 20;

    protected $fillable = ['ai_mission_id', 'created_by', 'source_message_id', 'text'];

    public function mission(): BelongsTo
    {
        return $this->belongsTo(AiMission::class, 'ai_mission_id');
    }
}
