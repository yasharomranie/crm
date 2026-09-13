<?php

namespace App\Filament\Resources\FormSubmissions\Schemas;

use App\Filament\Support\DynamicFormFieldBuilder;
use App\Models\FormTemplate;
use Filament\Forms\Components\Select;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

class FormSubmissionForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('form_template_id')
                    ->label('چه فرمی می‌خواهید ثبت کنید؟')
                    ->options(fn () => FormTemplate::query()->where('is_active', true)->pluck('title', 'id'))
                    ->required()
                    ->live()
                    ->searchable()
                    ->helperText('پس از انتخاب فرم، فیلدهای آن برای پرکردن نمایش داده می‌شود.'),

                // The template's own fields, rebuilt on every render from
                // the selection above - this is what makes any form an
                // admin designs in FormTemplateResource actually fillable.
                // No ->live() here: the closure already re-evaluates on
                // every request regardless, and marking the group itself
                // live caused an extra round-trip on every field
                // interaction that raced with the signature pad/GPS
                // picker's own async, JS-only-bound updates and silently
                // dropped them.
                Group::make()
                    ->components(function (Get $get) {
                        $templateId = $get('form_template_id');

                        if (! $templateId) {
                            return [];
                        }

                        return FormTemplate::query()
                            ->find($templateId)
                            ?->fields
                            ->map(fn ($field) => DynamicFormFieldBuilder::make($field))
                            ->all() ?? [];
                    }),
            ]);
    }
}
