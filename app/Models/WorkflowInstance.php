<?php

namespace App\Models;

use App\Enums\WorkflowActionType;
use App\Enums\WorkflowInstanceStatus;
use App\Exceptions\WorkflowException;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Facades\DB;

class WorkflowInstance extends Model
{
    use HasFactory;

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

    /**
     * @return Collection<int, User>
     */
    public function currentApprovers(): Collection
    {
        if (! $this->currentStep || $this->status !== WorkflowInstanceStatus::InProgress) {
            return new Collection;
        }

        return $this->currentStep->resolveApprovers($this->subject);
    }

    public function canBeActedOnBy(User $user): bool
    {
        return $this->status === WorkflowInstanceStatus::InProgress
            && $this->currentApprovers()->contains(fn (User $approver) => $approver->is($user));
    }

    /**
     * The single entry point for every approval decision. Records an
     * immutable WorkflowAction, then drives the instance (and, when the
     * subject is a FormSubmission, its status) forward.
     */
    public function act(User $actor, WorkflowActionType $type, ?string $comment = null): WorkflowAction
    {
        if ($this->status !== WorkflowInstanceStatus::InProgress) {
            throw WorkflowException::alreadyFinished();
        }

        if ($type === WorkflowActionType::Cancel) {
            if (! ($this->subject instanceof FormSubmission) || $this->subject->submitted_by !== $actor->id) {
                throw WorkflowException::onlySubmitterCanCancel();
            }
        } elseif (! $actor->hasRole('admin') && ! $this->canBeActedOnBy($actor)) {
            throw WorkflowException::notAnEligibleApprover();
        }

        if ($type === WorkflowActionType::ReferParallel && blank($comment)) {
            throw WorkflowException::commentRequiredForReferral();
        }

        return DB::transaction(function () use ($actor, $type, $comment) {
            $action = $this->actions()->create([
                'workflow_step_id' => $this->current_step_id,
                'actor_id' => $actor->id,
                'action' => $type,
                'comment' => $comment,
                'acted_at' => now(),
            ]);

            match ($type) {
                WorkflowActionType::Approve => $this->handleApprove(),
                WorkflowActionType::Reject => $this->finish(WorkflowInstanceStatus::Rejected),
                WorkflowActionType::Cancel => $this->finish(WorkflowInstanceStatus::Cancelled),
                WorkflowActionType::ReferParallel, WorkflowActionType::Comment => null, // no state change
            };

            return $action;
        });
    }

    private function handleApprove(): void
    {
        $next = $this->workflowDefinition->nextApplicableStepAfter($this->currentStep, $this->subject);

        if ($next) {
            $this->update(['current_step_id' => $next->id]);

            return;
        }

        $this->finish(WorkflowInstanceStatus::Approved);
    }

    private function finish(WorkflowInstanceStatus $status): void
    {
        $this->update(['status' => $status, 'completed_at' => now()]);

        if ($this->subject instanceof FormSubmission) {
            $this->subject->update(['status' => $status->toFormSubmissionStatus()]);
        }
    }
}
