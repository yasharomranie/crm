<?php

namespace App\Filament\Resources\FormSubmissions\Pages;

use App\Filament\Resources\FormSubmissions\FormSubmissionResource;
use App\Filament\Support\DynamicFormFieldBuilder;
use App\Models\FormSubmission;
use App\Models\FormTemplate;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class CreateFormSubmission extends CreateRecord
{
    protected static string $resource = FormSubmissionResource::class;

    protected static bool $canCreateAnother = false;

    /**
     * $data is a flat mix of "form_template_id" plus every dynamic field's
     * own key (the Group wrapper in FormSubmissionForm adds no state
     * namespace). Split it into the FormSubmission's own columns and one
     * FormSubmissionValue row per template field, then kick off the
     * workflow immediately - there is no separate "save as draft" step.
     */
    protected function handleRecordCreation(array $data): Model
    {
        $template = FormTemplate::query()->findOrFail($data['form_template_id']);

        return DB::transaction(function () use ($data, $template) {
            $submission = FormSubmission::create([
                'form_template_id' => $template->id,
                'form_template_version' => $template->version,
                'submitted_by' => Auth::id(),
                'department_id' => $template->department_id,
                'submitted_at' => now(),
            ]);

            foreach ($template->generalFields as $field) {
                if (! array_key_exists($field->field_key, $data)) {
                    continue;
                }

                $value = $data[$field->field_key];

                if ($value === null || $value === '') {
                    continue;
                }

                $submission->values()->create([
                    'form_field_id' => $field->id,
                    ...DynamicFormFieldBuilder::valueColumns($field, $value),
                ]);
            }

            $submission->load('values.formField');
            $submission->submit();

            return $submission;
        });
    }

    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl('view', ['record' => $this->getRecord()]);
    }
}
