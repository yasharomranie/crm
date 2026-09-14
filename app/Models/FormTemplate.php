<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FormTemplate extends Model
{
    use HasFactory;

    protected $attributes = [
        'version' => 1,
        'is_active' => true,
    ];

    protected $fillable = [
        'title', 'description', 'document_template', 'department_id', 'workflow_definition_id',
        'version', 'is_active', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function workflowDefinition(): BelongsTo
    {
        return $this->belongsTo(WorkflowDefinition::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function fields(): HasMany
    {
        return $this->hasMany(FormField::class)->orderBy('sort_order');
    }

    /**
     * Just the fields filled in at initial submission - excludes fields
     * scoped to a specific workflow step (e.g. "نظر دفتر فنی"), which are
     * filled in later by whoever acts at that step instead. See
     * FormField::workflowStep() and FormSubmissionForm.
     */
    public function generalFields(): HasMany
    {
        return $this->fields()->whereNull('workflow_step_id');
    }

    public function submissions(): HasMany
    {
        return $this->hasMany(FormSubmission::class);
    }
}
