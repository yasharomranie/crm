<?php

namespace App\Models;

use App\Enums\WorkflowInstanceStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class WorkflowInstance extends Model
{
    protected $fillable = [
        'workflow_definition_id', 'subject_id', 'subject_type',
        'current_step_id', 'status', 'started_at', 'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => WorkflowInstanceStatus::class,
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function workflowDefinition(): BelongsTo
    {
        return $this->belongsTo(WorkflowDefinition::class);
    }

    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    public function currentStep(): BelongsTo
    {
        return $this->belongsTo(WorkflowStep::class, 'current_step_id');
    }

    public function actions(): HasMany
    {
        return $this->hasMany(WorkflowAction::class)->orderBy('acted_at');
    }
}
