<?php

namespace App\Models;

use App\Enums\RoomStatus;
use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Enums\TaskType;
use App\Models\Concerns\HasTags;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Task extends Model
{
    use HasFactory, HasTags;

    protected $fillable = [
        'title', 'description', 'task_type', 'source_id', 'source_type',
        'parent_task_id', 'assigned_to', 'assigned_by', 'department_id',
        'priority', 'status', 'due_at', 'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'task_type' => TaskType::class,
            'priority' => TaskPriority::class,
            'status' => TaskStatus::class,
            'due_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function source(): MorphTo
    {
        return $this->morphTo();
    }

    public function parentTask(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_task_id');
    }

    public function subtasks(): HasMany
    {
        return $this->hasMany(self::class, 'parent_task_id');
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function assigner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function checklistItems(): HasMany
    {
        return $this->hasMany(TaskChecklistItem::class);
    }

    public function proofs(): HasMany
    {
        return $this->hasMany(TaskProof::class);
    }

    public function comments(): HasMany
    {
        return $this->hasMany(TaskComment::class);
    }

    public function workflowInstance(): MorphOne
    {
        return $this->morphOne(WorkflowInstance::class, 'subject');
    }

    protected static function booted(): void
    {
        static::updated(function (self $task) {
            if (! $task->wasChanged('status') || $task->status !== TaskStatus::Done) {
                return;
            }

            // Closing the loop: a housekeeping task spawned by a checkout
            // (see Booking::checkOut()) frees the room back to "available"
            // once it's actually done.
            if ($task->task_type === TaskType::FromBooking && $task->source instanceof Booking) {
                $task->source->room->update(['status' => RoomStatus::Available]);
            }
        });
    }
}
