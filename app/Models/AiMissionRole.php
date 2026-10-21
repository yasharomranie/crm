<?php

namespace App\Models;

use App\Enums\AiMissionRoleType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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

    public function profile(): BelongsTo
    {
        return $this->belongsTo(AiAssistantProfile::class, 'ai_assistant_profile_id');
    }
}
