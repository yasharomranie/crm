<?php

namespace App\Filament\Support;

use App\Enums\FormFieldType;
use App\Models\FormField;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Field;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\ViewField;

/**
 * Turns an admin-authored FormField (config/access-control.php's
 * "form-templates" module never touches this - it lives in
 * FormTemplateResource's Fields relation manager) into the matching live
 * Filament form component, so any form a template designer builds can
 * actually be filled in.
 */
class DynamicFormFieldBuilder
{
    public static function make(FormField $field): Field
    {
        $component = match ($field->field_type) {
            FormFieldType::Text => TextInput::make($field->field_key),
            FormFieldType::Textarea => Textarea::make($field->field_key),
            FormFieldType::Number => TextInput::make($field->field_key)->numeric(),
            FormFieldType::DateJalali => DatePicker::make($field->field_key)
                ->jalali()
                ->displayFormat('Y/m/d'),
            FormFieldType::Select => Select::make($field->field_key)
                ->options($field->options ?? [])
                ->native(false),
            FormFieldType::Radio => Radio::make($field->field_key)->options($field->options ?? []),
            FormFieldType::Checkbox => Checkbox::make($field->field_key),
            // No ->disk()/->visibility() here: this stores through the
            // app's default disk (config/filesystems.php - 'local' in dev,
            // a private Cloud Object Storage bucket in production), which
            // is required for uploads to actually survive a deploy/scale
            // event there. Passing an explicit disk name defeats that -
            // see FormSubmissionFileController for the download side of
            // this same fix.
            FormFieldType::File => FileUpload::make($field->field_key)
                ->directory('form-submissions'),
            FormFieldType::Signature => ViewField::make($field->field_key)
                ->view('filament.forms.signature-pad'),
            FormFieldType::Gps => ViewField::make($field->field_key)
                ->view('filament.forms.gps-picker'),
        };

        return $component
            ->label($field->label)
            ->required((bool) $field->is_required)
            ->rules($field->validation_rules ?? [])
            ->columnSpanFull();
    }

    /**
     * Splits one submitted value into the correctly typed column(s) for a
     * FormSubmissionValue row - the mirror image of make() above.
     *
     * @return array<string, mixed>
     */
    public static function valueColumns(FormField $field, mixed $submitted): array
    {
        return match ($field->field_type) {
            FormFieldType::Number => ['value_number' => $submitted],
            FormFieldType::DateJalali => ['value_date' => $submitted],
            FormFieldType::Checkbox => ['value_json' => (bool) $submitted],
            FormFieldType::Gps => ['value_json' => is_string($submitted) ? json_decode($submitted, true) : $submitted],
            FormFieldType::File => ['file_path' => $submitted],
            default => ['value_text' => $submitted],
        };
    }
}
