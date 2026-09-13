<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WorkflowDefinition extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'subject_type', 'is_active'];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function steps(): HasMany
    {
        return $this->hasMany(WorkflowStep::class)->orderBy('step_order');
    }

    public function instances(): HasMany
    {
        return $this->hasMany(WorkflowInstance::class);
    }

    /**
     * The first step whose condition_rules match this submission - steps
     * skipped by "smart referral" conditions are simply never reached.
     */
    public function firstApplicableStep(FormSubmission $submission): ?WorkflowStep
    {
        return $this->steps->first(fn (WorkflowStep $step) => $step->appliesTo($submission));
    }

    /**
     * The next applicable step after $current, or null once there are no
     * more - meaning the workflow is finished (approved).
     */
    public function nextApplicableStepAfter(WorkflowStep $current, FormSubmission $submission): ?WorkflowStep
    {
        return $this->steps
            ->where('step_order', '>', $current->step_order)
            ->first(fn (WorkflowStep $step) => $step->appliesTo($submission));
    }
}
