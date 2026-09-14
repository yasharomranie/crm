<?php

namespace App\Models;

use App\Enums\ApproverType;
use App\Enums\FormSubmissionStatus;
use App\Enums\WorkflowInstanceStatus;
use App\Models\Concerns\HasTags;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;

class FormSubmission extends Model
{
    use HasFactory, HasTags;

    protected $fillable = [
        'reference_code', 'form_template_id', 'form_template_version',
        'submitted_by', 'department_id', 'status', 'submitted_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => FormSubmissionStatus::class,
            'submitted_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $submission) {
            $submission->reference_code ??= static::generateReferenceCode();
        });
    }

    public static function generateReferenceCode(): string
    {
        $sequence = str_pad((string) (static::max('id') + 1), 6, '0', STR_PAD_LEFT);

        return "GNJ-FRM-{$sequence}";
    }

    public function formTemplate(): BelongsTo
    {
        return $this->belongsTo(FormTemplate::class);
    }

    public function submitter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    /**
     * Resolved through the submission's department rather than a
     * dedicated column - see Document::company() for the same reasoning.
     */
    public function company(): ?Company
    {
        return $this->department?->company;
    }

    public function values(): HasMany
    {
        return $this->hasMany(FormSubmissionValue::class);
    }

    public function workflowInstance(): MorphOne
    {
        return $this->morphOne(WorkflowInstance::class, 'subject');
    }

    /**
     * Tasks spawned from this submission (e.g. an inspection form that
     * creates a repair task).
     */
    public function tasks(): MorphMany
    {
        return $this->morphMany(Task::class, 'source');
    }

    /**
     * The submitted value for a given form field key - used by
     * WorkflowStep::appliesTo() to evaluate "smart referral" conditions
     * (e.g. only route to a second approver when amount > X).
     */
    public function fieldValue(string $fieldKey): mixed
    {
        return $this->values
            ->first(fn (FormSubmissionValue $value) => $value->formField?->field_key === $fieldKey)
            ?->value;
    }

    /**
     * Locks in a Draft submission: starts its workflow (if the template
     * has one) at the first applicable step, or - if there is no
     * workflow, or every step is skipped by its own conditions - marks
     * the submission Approved immediately with nothing left to review.
     */
    public function submit(): void
    {
        $definition = $this->formTemplate->workflowDefinition;
        $firstStep = $definition?->firstApplicableStep($this);

        if (! $definition || ! $firstStep) {
            $this->update(['status' => FormSubmissionStatus::Approved]);

            return;
        }

        $this->workflowInstance()->create([
            'workflow_definition_id' => $definition->id,
            'current_step_id' => $firstStep->id,
            'status' => WorkflowInstanceStatus::InProgress,
            'started_at' => now(),
        ]);

        $this->update(['status' => FormSubmissionStatus::PendingApproval]);
    }

    public function isVisibleTo(User $user): bool
    {
        if ($user->hasRole('admin') || $user->can('form-submissions.view_any')) {
            return true;
        }

        if ($this->submitted_by === $user->id) {
            return true;
        }

        return $this->workflowInstance?->actions()->where('actor_id', $user->id)->exists()
            || ($this->workflowInstance?->canBeActedOnBy($user) ?? false);
    }

    /**
     * Scopes a listing to: my own submissions, submissions I've acted (or
     * can currently act) on, or everything if I hold the elevated
     * "view_any" permission - the same three cases isVisibleTo() checks,
     * expressed in SQL instead of loading every row.
     */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if ($user->hasRole('admin') || $user->can('form-submissions.view_any')) {
            return $query;
        }

        return $query->where(function (Builder $query) use ($user) {
            $query->where('submitted_by', $user->id)
                ->orWhereHas('workflowInstance.actions', fn (Builder $q) => $q->where('actor_id', $user->id))
                ->orWhere(fn (Builder $q) => static::scopeAwaitingActionFromInto($q, $user));
        });
    }

    /**
     * Just the "waiting for my decision right now" slice - the actual
     * کارتابل ورودی tab. Resolves ApproverType::Role/SpecificUser directly
     * in SQL; DepartmentManager is resolved via the manager's own managed
     * department ids, since it depends on *this* submission's department.
     */
    public function scopeAwaitingActionFrom(Builder $query, User $user): Builder
    {
        return $query->where(fn (Builder $q) => static::scopeAwaitingActionFromInto($q, $user));
    }

    protected static function scopeAwaitingActionFromInto(Builder $query, User $user): Builder
    {
        $roleIds = $user->roles()->pluck('id');
        $managedDepartmentIds = Department::query()->where('manager_id', $user->id)->pluck('id');

        return $query->whereHas('workflowInstance', function (Builder $instance) use ($user, $roleIds) {
            $instance->where('status', WorkflowInstanceStatus::InProgress)
                ->whereHas('currentStep', function (Builder $step) use ($user, $roleIds) {
                    $step->where(fn (Builder $w) => $w->where('approver_type', ApproverType::SpecificUser)
                        ->where('approver_user_id', $user->id))
                        ->orWhere(fn (Builder $w) => $w->where('approver_type', ApproverType::Role)
                            ->whereIn('approver_role_id', $roleIds));
                });
        })->when($managedDepartmentIds->isNotEmpty(), function (Builder $query) use ($managedDepartmentIds) {
            $query->orWhere(function (Builder $deptBranch) use ($managedDepartmentIds) {
                $deptBranch->whereIn('department_id', $managedDepartmentIds)
                    ->whereHas('workflowInstance', function (Builder $instance) {
                        $instance->where('status', WorkflowInstanceStatus::InProgress)
                            ->whereHas('currentStep', fn (Builder $step) => $step->where('approver_type', ApproverType::DepartmentManager));
                    });
            });
        });
    }
}
