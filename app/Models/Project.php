<?php

namespace App\Models;

use App\Enums\ProjectStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

class Project extends Model
{
    protected $fillable = [
        'code', 'title', 'description', 'department_id', 'owner_id',
        'budget', 'status', 'start_date', 'end_date', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'budget' => 'decimal:0',
            'status' => ProjectStatus::class,
            'start_date' => 'date',
            'end_date' => 'date',
        ];
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    /**
     * Resolved through the project's department rather than a dedicated
     * column - see Document::company() for the same reasoning.
     */
    public function company(): ?Company
    {
        return $this->department?->company;
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function phases(): HasMany
    {
        return $this->hasMany(ProjectPhase::class);
    }

    public function rootPhases(): HasMany
    {
        return $this->phases()->whereNull('parent_id')->orderBy('sort_order');
    }

    /**
     * Only leaf WBS nodes (no children) carry a meaningful weight/progress -
     * "phase" container nodes just organize the tree and are excluded here,
     * so their weight is never double-counted with their children's.
     */
    public function leafPhases(): HasMany
    {
        return $this->phases()->whereDoesntHave('children');
    }

    public function expenses(): HasMany
    {
        return $this->hasMany(ProjectExpense::class);
    }

    /**
     * EV% - weighted physical progress across all leaf WBS items (0-100).
     */
    public function earnedValuePercent(): float
    {
        return (float) $this->leafPhases()->get()
            ->sum(fn (ProjectPhase $phase) => $phase->weight * $phase->progress_percent / 100);
    }

    /**
     * PV% - what progress *should* be, today, based on each leaf item's own
     * planned start/end dates (0-100).
     */
    public function plannedValuePercent(?Carbon $asOf = null): float
    {
        $asOf ??= now();

        return (float) $this->leafPhases()->get()
            ->sum(fn (ProjectPhase $phase) => $phase->weight * $phase->plannedProgressPercent($asOf) / 100);
    }

    public function earnedValue(): float
    {
        return (float) $this->budget * $this->earnedValuePercent() / 100;
    }

    public function plannedValue(?Carbon $asOf = null): float
    {
        return (float) $this->budget * $this->plannedValuePercent($asOf) / 100;
    }

    public function actualCost(): float
    {
        return (float) $this->expenses()->sum('amount');
    }

    /**
     * CV: positive = under budget for the work actually done, negative = over.
     */
    public function costVariance(): float
    {
        return $this->earnedValue() - $this->actualCost();
    }

    /**
     * SV: positive = ahead of schedule, negative = behind.
     */
    public function scheduleVariance(): float
    {
        return $this->earnedValue() - $this->plannedValue();
    }

    /**
     * Sum of leaf-item weights - should be ~100 for the EVM numbers above to
     * be meaningful; surfaced in the UI as a sanity check for whoever builds
     * the WBS.
     */
    public function totalLeafWeight(): float
    {
        return (float) $this->leafPhases()->sum('weight');
    }

    /**
     * A simplified critical path: the longest finish-to-start chain of
     * phases by duration (days), found by walking each phase's single
     * `predecessor_id` link. This is CPM's core idea without float/slack
     * calculation - good enough to highlight "these are the activities that
     * cannot slip without delaying the project."
     *
     * @return array<int> phase IDs along the critical chain, in order
     */
    public function criticalPathPhaseIds(): array
    {
        $phases = $this->phases()->get()->keyBy('id');
        $longest = [];

        $computeLongest = function (ProjectPhase $phase) use (&$computeLongest, &$longest, $phases): array {
            if (isset($longest[$phase->id])) {
                return $longest[$phase->id];
            }

            $duration = ($phase->start_date && $phase->end_date)
                ? $phase->start_date->diffInDays($phase->end_date) + 1
                : 0;

            if ($phase->predecessor_id && $phases->has($phase->predecessor_id)) {
                [$predecessorLength, $predecessorChain] = $computeLongest($phases[$phase->predecessor_id]);
                $result = [$predecessorLength + $duration, [...$predecessorChain, $phase->id]];
            } else {
                $result = [$duration, [$phase->id]];
            }

            return $longest[$phase->id] = $result;
        };

        $best = [0, []];

        foreach ($phases as $phase) {
            $result = $computeLongest($phase);

            if ($result[0] > $best[0]) {
                $best = $result;
            }
        }

        return $best[1];
    }
}
