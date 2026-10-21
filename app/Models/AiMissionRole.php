<?php

namespace App\Models;

use App\Enums\AiMissionRoleType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class AiMissionRole extends Model
{
    use HasFactory;

    protected $fillable = ['ai_mission_id', 'ai_assistant_profile_id', 'role', 'directive', 'sort'];

    protected function casts(): array
    {
        return ['role' => AiMissionRoleType::class];
    }

    public function mission(): BelongsTo
    {
        return $this->belongsTo(AiMission::class, 'ai_mission_id');
    }

    /**
     * Files the owner limited this member to; none means every file of the mission.
     */
    public function sources(): BelongsToMany
    {
        return $this->belongsToMany(AiMissionSource::class, 'ai_mission_role_source');
    }

    public function profile(): BelongsTo
    {
        return $this->belongsTo(AiAssistantProfile::class, 'ai_assistant_profile_id');
    }
}
