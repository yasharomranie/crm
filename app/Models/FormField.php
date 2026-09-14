<?php

namespace App\Models;

use App\Enums\FormFieldType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FormField extends Model
{
    use HasFactory;

    protected $fillable = [
        'form_template_id', 'field_key', 'label', 'field_type',
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
     * form_submission_values.form_field_id is cascadeOnDelete() - deleting
     * this field permanently deletes every submitted answer for it, across
     * every past submission. FieldsRelationManager uses this to warn (with
     * the real count) before that happens instead of doing it silently.
     */
    public function values(): HasMany
    {
        return $this->hasMany(FormSubmissionValue::class);
    }
}
