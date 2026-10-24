<?php

namespace App\Models;

use App\Enums\AiMissionMessageKind;
use App\Enums\AiMissionRoleType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiMissionMessage extends Model
{
    use HasFactory;

    protected $fillable = ['ai_mission_thread_id', 'ai_assistant_profile_id', 'role', 'kind', 'body', 'tokens', 'rating', 'meta'];

    protected function casts(): array
    {
        return [
            'role' => AiMissionRoleType::class,
            'kind' => AiMissionMessageKind::class,
            'meta' => 'array',
        ];
    }

    public function thread(): BelongsTo
    {
        return $this->belongsTo(AiMissionThread::class, 'ai_mission_thread_id');
    }

    public function profile(): BelongsTo
    {
        return $this->belongsTo(AiAssistantProfile::class, 'ai_assistant_profile_id');
    }
}
