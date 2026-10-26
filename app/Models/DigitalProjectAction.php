<?php

namespace App\Models;

use App\Enums\DigitalProjectActionStatus;
use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Enums\TaskType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphOne;

/**
 * A concrete follow-up on one digital project ("renew the SSL", "post the
 * spring campaign"). Kept apart from the CRM Task on purpose.
 */
class DigitalProjectAction extends Model
{
    use HasFactory;

    protected $fillable = ['digital_project_id', 'title', 'details', 'status', 'due_on', 'assigned_to', 'completed_at', 'created_by'];

    protected function casts(): array
    {
        return [
            'status' => DigitalProjectActionStatus::class,
            'due_on' => 'date',
            'completed_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (self $action): void {
            $isDone = $action->status === DigitalProjectActionStatus::Done;

            if ($isDone && $action->completed_at === null) {
                $action->completed_at = now();
            } elseif (! $isDone) {
                $action->completed_at = null;
            }
        });
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(DigitalProject::class, 'digital_project_id');
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    /** The CRM task this action was turned into, if it was. */
    public function task(): MorphOne
    {
        return $this->morphOne(Task::class, 'source');
    }

    /**
     * Turns the action into a regular task (same title, details, assignee and
     * deadline) so it shows up in the team's task lists; once only.
     */
    public function convertToTask(?int $assignedBy): Task
    {
        return $this->task()->firstOr(fn () => $this->task()->create([
            'title' => $this->project->name.': '.$this->title,
            'description' => $this->details,
            'task_type' => TaskType::Manual,
            'priority' => TaskPriority::Neither,
            'status' => TaskStatus::Todo,
            'assigned_to' => $this->assigned_to,
            'assigned_by' => $assignedBy,
            'due_at' => $this->due_on?->endOfDay(),
        ]));
    }

    public function isOverdue(): bool
    {
        return $this->status !== DigitalProjectActionStatus::Done && $this->due_on !== null && $this->due_on->lt(today());
    }
}
