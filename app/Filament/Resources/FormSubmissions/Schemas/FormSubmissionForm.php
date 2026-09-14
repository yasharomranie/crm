<?php

namespace App\Filament\Resources\FormSubmissions\Schemas;

use App\Filament\Support\DynamicFormFieldBuilder;
use App\Models\FormField;
use App\Models\FormTemplate;
use Filament\Forms\Components\Select;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
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
                    // Lets a "تکمیل با فرم" link (e.g. from a checklist-
                    // generated task) land here with the right form already
                    // selected, instead of making the user pick it again.
                    ->default(fn () => request()->integer('form_template_id') ?: null)
                    ->required()
                    ->live()
                    ->searchable()
                    ->helperText('پس از انتخاب فرم، فیلدهای آن برای پرکردن نمایش داده می‌شود.')
                    // Seeds each dynamic field's Livewire state key the
                    // moment a template is (re)selected. Without this, a
                    // field the Group below builds in this same response
                    // (the GPS picker, signature pad, or a Jalali date
                    // picker - anything bound via $wire.$entangle() instead
                    // of a plain wire:model) tries to entangle a property
                    // that doesn't exist yet on the component, which
                    // Livewire silently refuses ("Livewire property [...]
                    // cannot be found" in the browser console). The field
                    // then LOOKS filled in (Alpine's own local state updates
                    // fine), but the value never reaches the server, so a
                    // required rule on it still fails on submit even though
                    // the user picked a value.
                    ->afterStateUpdated(function (Set $set, ?int $state) {
                        if (! $state) {
                            return;
                        }

                        FormTemplate::find($state)?->generalFields->each(
                            fn (FormField $field) => $set($field->field_key, null)
                        );
                    }),

                // The template's own general fields (workflow_step_id is
                // null), rebuilt on every render from the selection above -
                // this is what makes any form an admin designs in
                // FormTemplateResource actually fillable. Fields scoped to
                // a specific workflow step (e.g. "نظر دفتر فنی") are
                // deliberately excluded here - they're filled in later, by
                // whoever acts at that step (see ViewFormSubmission).
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
                            ?->generalFields
                            ->map(fn ($field) => DynamicFormFieldBuilder::make($field))
                            ->all() ?? [];
                    }),
            ]);
    }
}
