<?php

namespace App\Models;

use App\Enums\DealStage;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * "When a deal enters {trigger_stage}, schedule a follow-up task due
 * {due_after_hours} hours later" - applied by
 * App\Domain\Crm\DealAutomationService whenever a deal is created in, or
 * moved to, that stage.
 */
class DealAutomationRule extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'trigger_stage', 'task_title', 'due_after_hours', 'assigned_to', 'is_active'];

    protected function casts(): array
    {
        return [
            'trigger_stage' => DealStage::class,
            'due_after_hours' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }
}
