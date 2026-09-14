<?php

namespace App\Models;

use App\Enums\ApproverType;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Permission\Models\Role;

class WorkflowStep extends Model
{
    use HasFactory;

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

    /**
     * Form fields that are only filled in by whoever acts at this step
     * (e.g. "نظر دفتر فنی"), not by the original submitter - see
     * FormField::workflowStep().
     */
    public function fields(): HasMany
    {
        return $this->hasMany(FormField::class)->orderBy('sort_order');
    }

    /**
     * Smart referral: a step only takes part in a given submission's
     * approval chain if all its condition_rules match. Each rule is
     * ['field' => form field key, 'operator' => one of ==,!=,>,>=,<,<=,
     * 'value' => scalar]. No rules (null/empty) means the step always
     * applies - this is what makes most workflows unconditional. Each
     * form field's condition_rules are stored the same shape:
     * array<int, array{field: string, operator: string, value: mixed}>.
     */
    public function appliesTo(FormSubmission $submission): bool
    {
        $rules = $this->condition_rules ?? [];

        if ($rules === []) {
            return true;
        }

        foreach ($rules as $rule) {
            $actual = $submission->fieldValue($rule['field'] ?? '');

            if (! self::compare($actual, $rule['operator'] ?? '==', $rule['value'] ?? null)) {
                return false;
            }
        }

        return true;
    }

    private static function compare(mixed $actual, string $operator, mixed $expected): bool
    {
        if (is_numeric($actual) && is_numeric($expected)) {
            $actual = (float) $actual;
            $expected = (float) $expected;
        }

        return match ($operator) {
            '==' => $actual == $expected,
            '!=' => $actual != $expected,
            '>' => $actual > $expected,
            '>=' => $actual >= $expected,
            '<' => $actual < $expected,
            '<=' => $actual <= $expected,
            default => false,
        };
    }

    /**
     * The users who may act (approve/reject/refer) on this step for the
     * given submission - resolved dynamically per approver_type.
     *
     * @return Collection<int, User>
     */
    public function resolveApprovers(FormSubmission $submission): Collection
    {
        return match ($this->approver_type) {
            ApproverType::SpecificUser => $this->approverUser
                ? new Collection([$this->approverUser])
                : new Collection,
            ApproverType::Role => $this->approverRole?->users ?? new Collection,
            ApproverType::DepartmentManager => $submission->department?->manager
                ? new Collection([$submission->department->manager])
                : new Collection,
        };
    }
}
