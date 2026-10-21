<?php

namespace App\Models;

use App\Enums\AiMissionSourceKind;
use App\Enums\AiMissionSourceStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A file (strategy, policy, report, spreadsheet) in a user's library of
 * mission sources. Its text is extracted and digested once; missions attach
 * the sources they need, so one document can serve several missions.
 */
class AiMissionSource extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id', 'title', 'original_filename', 'extension', 'kind', 'path', 'content_hash', 'char_count',
        'status', 'error', 'content', 'digest', 'tokens_used', 'processed_at',
    ];

    protected $hidden = ['content'];

    protected function casts(): array
    {
        return [
            'kind' => AiMissionSourceKind::class,
            'status' => AiMissionSourceStatus::class,
            'digest' => 'array',
            'processed_at' => 'datetime',
        ];
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function chunks(): HasMany
    {
        return $this->hasMany(AiMissionSourceChunk::class)->orderBy('position');
    }

    public function missions(): BelongsToMany
    {
        return $this->belongsToMany(AiMission::class, 'ai_mission_source_mission')->withTimestamps();
    }

    public function scopeOwnedBy(Builder $query, User $user): Builder
    {
        return $query->where('user_id', $user->id);
    }
}
