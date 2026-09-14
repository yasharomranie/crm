<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FormSubmissionValue extends Model
{
    use HasFactory;

    protected $fillable = [
        'form_submission_id', 'form_field_id',
        'value_text', 'value_number', 'value_date', 'value_json', 'file_path',
    ];

    protected function casts(): array
    {
        return [
            // Without these, some MySQL/PDO driver configurations return
            // foreign key columns as strings instead of ints - harmless for
            // Eloquent relations (which use loose comparison internally),
            // but deadly for any ===/!== check against another model's id
            // (FormSubmissionFileController::show() compares this against
            // FormSubmission::$id with ===, which silently 404s a real,
            // correctly-stored file the moment this column comes back as a
            // string on a server where it did).
            'form_submission_id' => 'integer',
            'form_field_id' => 'integer',
            'value_number' => 'decimal:4',
            'value_date' => 'date',
            'value_json' => 'array',
        ];
    }

    public function formSubmission(): BelongsTo
    {
        return $this->belongsTo(FormSubmission::class);
    }

    public function formField(): BelongsTo
    {
        return $this->belongsTo(FormField::class);
    }

    /**
     * Resolve the value regardless of which typed column it lives in,
     * based on the parent field's type.
     */
    public function getValueAttribute(): mixed
    {
        return match (true) {
            $this->value_json !== null => $this->value_json,
            $this->value_number !== null => $this->value_number,
            $this->value_date !== null => $this->value_date,
            default => $this->value_text,
        };
    }
}
