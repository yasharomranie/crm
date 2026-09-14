<?php

namespace App\Models;

use App\Enums\FormFieldType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class FormField extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'form_template_id', 'workflow_step_id', 'field_key', 'label', 'field_type',
        'options', 'validation_rules', 'conditional_logic', 'sort_order', 'is_required',
    ];

    protected function casts(): array
    {
        return [
            'field_type' => FormFieldType::class,
            'options' => 'array',
            'validation_rules' => 'array',
            'conditional_logic' => 'array',
            'is_required' => 'boolean',
        ];
    }

    public function formTemplate(): BelongsTo
    {
        return $this->belongsTo(FormTemplate::class);
    }

    /**
     * When set, this field isn't filled in at initial submission - it's
     * filled in by whoever acts (approves/rejects) at this specific step,
     * e.g. "نظر دفتر فنی" only appearing on the "تأیید دفتر فنی" step's
     * own action form. Null (the default) means "filled at submission",
     * like every field before this existed.
     */
    public function workflowStep(): BelongsTo
    {
        return $this->belongsTo(WorkflowStep::class);
    }

    /**
     * Every submitted answer for this field, across every past submission.
     * Because FormField soft-deletes, removing a field from the form
     * builder never cascades into deleting these - they stay put, and old
     * submissions keep showing them (see form-submission-detail.blade.php,
     * which loads fields ->withTrashed() for exactly this reason).
     */
    public function values(): HasMany
    {
        return $this->hasMany(FormSubmissionValue::class);
    }
}
