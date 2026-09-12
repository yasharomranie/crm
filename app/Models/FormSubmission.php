<?php

namespace App\Models;

use App\Enums\FormSubmissionStatus;
use App\Models\Concerns\HasTags;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;

class FormSubmission extends Model
{
    use HasTags;

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
}
