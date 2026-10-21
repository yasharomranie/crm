<?php

namespace App\Models;

use App\Enums\AiMissionBriefStatus;
use App\Enums\AiMissionStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A job handed to a small team of assistants: a goal, roles with directives,
 * a board of findings and a draft report for the manager to decide on. The
 * team only reads and reports - nothing here changes business data.
 */
class AiMission extends Model
{
    use HasFactory;

    protected $fillable = [
        'created_by', 'title', 'goal', 'end_condition', 'status', 'max_rounds', 'rounds_run',
        'report', 'notes', 'last_error', 'started_at', 'finished_at', 'cancel_requested', 'current_step', 'current_profile_id',
        'step_index', 'step_total', 'tokens_used', 'token_budget', 'schedule', 'schedule_time', 'schedule_weekday',
        'schedule_day', 'next_run_at', 'last_scheduled_at', 'run_trigger', 'budget_alerted',
    ];

    protected function casts(): array
    {
        return [
            'status' => AiMissionStatus::class,
            'report' => 'array',
            'notes' => 'array',
            'brief' => 'array',
            'brief_status' => AiMissionBriefStatus::class,
            'brief_approved_at' => 'datetime',
            'cancel_requested' => 'boolean',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
            'next_run_at' => 'datetime',
            'last_scheduled_at' => 'datetime',
            'budget_alerted' => 'boolean',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function roles(): HasMany
    {
        return $this->hasMany(AiMissionRole::class)->orderBy('sort')->orderBy('id');
    }

    public function runs(): HasMany
    {
        return $this->hasMany(AiMissionRun::class)->latest('finished_at')->latest('id');
    }

    public function sources(): BelongsToMany
    {
        return $this->belongsToMany(AiMissionSource::class, 'ai_mission_source_mission')->withTimestamps()->orderBy('ai_mission_sources.title');
    }

    public function actions(): HasMany
    {
        return $this->hasMany(AiMissionAction::class)->orderBy('id');
    }

    public function cards(): HasMany
    {
        return $this->hasMany(AiMissionCard::class)->orderBy('round')->orderBy('id');
    }

    public function scopeOwnedBy(Builder $query, User $user): Builder
    {
        return $query->where('created_by', $user->id);
    }

    public function canRunAnotherRound(): bool
    {
        // A run that died mid-way leaves "running" behind; after ten quiet minutes it may be retried.
        $stuck = $this->status === AiMissionStatus::Running && $this->updated_at->lt(now()->subMinutes(10));

        return ($this->status !== AiMissionStatus::Running || $stuck) && $this->rounds_run < $this->max_rounds;
    }
}
