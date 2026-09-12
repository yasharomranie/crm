<?php

namespace App\Models;

use App\Enums\ProjectPhaseType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

class ProjectPhase extends Model
{
    protected $fillable = [
        'project_id', 'parent_id', 'predecessor_id', 'title', 'type',
        'weight', 'progress_percent', 'start_date', 'end_date', 'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'type' => ProjectPhaseType::class,
            'weight' => 'decimal:2',
            'start_date' => 'date',
            'end_date' => 'date',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('sort_order');
    }

    public function predecessor(): BelongsTo
    {
        return $this->belongsTo(self::class, 'predecessor_id');
    }

    public function successors(): HasMany
    {
        return $this->hasMany(self::class, 'predecessor_id');
    }

    /**
     * Where physical progress "should" be today, based on this item's own
     * planned start/end (linear interpolation) - 0 before it starts, 100
     * once its planned end has passed.
     */
    public function plannedProgressPercent(Carbon $asOf): float
    {
        if (! $this->start_date || ! $this->end_date) {
            return 0.0;
        }

        if ($asOf->lt($this->start_date)) {
            return 0.0;
        }

        if ($asOf->gte($this->end_date)) {
            return 100.0;
        }

        $totalDays = $this->start_date->diffInDays($this->end_date) ?: 1;
        $elapsedDays = $this->start_date->diffInDays($asOf);

        return min(100.0, max(0.0, ($elapsedDays / $totalDays) * 100));
    }

    public function isOverdue(): bool
    {
        return $this->end_date
            && $this->end_date->isPast()
            && $this->progress_percent < 100;
    }
}
