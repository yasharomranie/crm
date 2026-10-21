<?php

namespace App\Models;

use App\Enums\AiMissionSourceKind;
use App\Enums\AiMissionSourceStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

/**
 * A file (strategy, policy, report, spreadsheet) in one mission's own
 * library. Its text is extracted and digested once; the mission's strategy
 * brief and the team's file search are built from it. A file is added while a
 * mission is being set up before the mission exists (ai_mission_id is null
 * until it is created) and is removed with the mission.
 */
class AiMissionSource extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id', 'ai_mission_id', 'title', 'original_filename', 'extension', 'kind', 'path', 'content_hash', 'char_count',
        'status', 'error', 'content', 'digest', 'tokens_used', 'processed_at',
    ];

    protected $hidden = ['content'];

    protected static function booted(): void
    {
        static::deleting(function (self $source): void {
            if ($source->path) {
                Storage::disk('local')->delete($source->path);
            }
        });
    }

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

    public function mission(): BelongsTo
    {
        return $this->belongsTo(AiMission::class, 'ai_mission_id');
    }

    public function scopeOwnedBy(Builder $query, User $user): Builder
    {
        return $query->where('user_id', $user->id);
    }
}
