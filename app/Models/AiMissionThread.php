<?php

namespace App\Models;

use App\Enums\AiMissionThreadStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One conversation between the manager and a mission's standing team: a
 * request, the members' short replies and the coordinator's answer, plus the
 * follow-ups. `stage` and `plan` remember where a running (or failed)
 * conversation is, so it can continue without paying for finished steps twice.
 */
class AiMissionThread extends Model
{
    use HasFactory;

    protected $fillable = ['ai_mission_id', 'created_by', 'title', 'status', 'stage', 'plan', 'tokens_used', 'last_error'];

    protected function casts(): array
    {
        return [
            'status' => AiMissionThreadStatus::class,
            'plan' => 'array',
        ];
    }

    public function mission(): BelongsTo
    {
        return $this->belongsTo(AiMission::class, 'ai_mission_id');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(AiMissionMessage::class)->orderBy('id');
    }
}
