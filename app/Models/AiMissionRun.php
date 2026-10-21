<?php

namespace App\Models;

use App\Enums\AiMissionStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A finished round of a mission, kept as it was reported: a recurring mission
 * clears its board each cycle, so this is where earlier results stay readable.
 */
class AiMissionRun extends Model
{
    use HasFactory;

    protected $fillable = ['ai_mission_id', 'trigger', 'round', 'status', 'summary', 'report', 'tokens_used', 'finished_at'];

    protected function casts(): array
    {
        return [
            'status' => AiMissionStatus::class,
            'report' => 'array',
            'finished_at' => 'datetime',
        ];
    }

    public function mission(): BelongsTo
    {
        return $this->belongsTo(AiMission::class, 'ai_mission_id');
    }
}
