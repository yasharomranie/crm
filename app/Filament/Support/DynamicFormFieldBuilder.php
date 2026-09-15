<?php

namespace App\Filament\Support;

use App\Enums\FormFieldType;
use App\Enums\TableColumnType;
use App\Models\FormField;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Field;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Repeater\TableColumn;
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
            // Mirrors a physical paper form's own repeating table (e.g. a
            // 16-row "معوقات اجاره" grid) instead of forcing an admin to
            // fake it with dozens of individually-numbered flat fields -
            // ->table() renders it as an actual spreadsheet-like grid, one
            // column per entry in options.columns, rather than the
            // stacked card layout a plain Repeater defaults to.
            FormFieldType::Table => static::buildTableRepeater($field),
        };

        return $component
            ->label($field->label)
            ->required((bool) $field->is_required)
            ->rules($field->validation_rules ?? [])
            ->columnSpanFull();
    }

    /**
     * @return array<int, array{key: string, label: string, type: TableColumnType}>
     */
    private static function tableColumns(FormField $field): array
    {
        return collect($field->options['columns'] ?? [])
            ->map(fn (array $column) => [
                'key' => $column['key'],
                'label' => $column['label'],
                'type' => TableColumnType::from($column['type'] ?? TableColumnType::Text->value),
            ])
            ->all();
    }

    private static function buildTableRepeater(FormField $field): Repeater
    {
        $columns = static::tableColumns($field);

        return Repeater::make($field->field_key)
            ->table(array_map(fn (array $column) => TableColumn::make($column['label']), $columns))
            ->schema(array_map(fn (array $column) => match ($column['type']) {
                TableColumnType::Number => TextInput::make($column['key'])->numeric(),
                TableColumnType::DateJalali => DatePicker::make($column['key'])->jalali()->displayFormat('Y/m/d'),
                TableColumnType::Text => TextInput::make($column['key']),
            }, $columns))
            ->defaultItems((int) ($field->options['default_rows'] ?? 0))
            ->addActionLabel('افزودن ردیف')
            ->reorderable(false);
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
            // array_values(): a Repeater's raw state is keyed by internal
            // item UUIDs that mean nothing once detached from the live
            // component - stored as a plain sequential array of rows
            // instead, which is also what every reader (the merged
            // document, print/word exports, the detail view) expects to
            // foreach() over.
            FormFieldType::Table => ['value_json' => array_values($submitted ?? [])],
            default => ['value_text' => $submitted],
        };
    }
}
