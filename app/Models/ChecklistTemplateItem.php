<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChecklistTemplateItem extends Model
{
    protected $fillable = ['task_checklist_template_id', 'label', 'sort_order'];

    public function template(): BelongsTo
    {
        return $this->belongsTo(TaskChecklistTemplate::class, 'task_checklist_template_id');
    }
}
