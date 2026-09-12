<?php

namespace App\Models;

use App\Enums\ApproverType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Permission\Models\Role;

class WorkflowStep extends Model
{
    protected $fillable = [
        'workflow_definition_id', 'step_order', 'name', 'approver_type',
        'approver_role_id', 'approver_user_id', 'sla_hours', 'condition_rules',
    ];

    protected function casts(): array
    {
        return [
            'approver_type' => ApproverType::class,
            'condition_rules' => 'array',
        ];
    }

    public function workflowDefinition(): BelongsTo
    {
        return $this->belongsTo(WorkflowDefinition::class);
    }

    public function approverRole(): BelongsTo
    {
        return $this->belongsTo(Role::class, 'approver_role_id');
    }

    public function approverUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approver_user_id');
    }
}
