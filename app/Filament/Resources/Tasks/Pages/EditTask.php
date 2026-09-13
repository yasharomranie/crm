<?php

namespace App\Filament\Resources\Tasks\Pages;

use App\Filament\Resources\FormSubmissions\FormSubmissionResource;
use App\Filament\Resources\Tasks\TaskResource;
use App\Models\TaskChecklistTemplate;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Icons\Heroicon;

class EditTask extends EditRecord
{
    protected static string $resource = TaskResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('completeWithForm')
                ->label('تکمیل با فرم')
                ->icon(Heroicon::OutlinedDocumentText)
                ->color('primary')
                ->visible(fn () => filled($this->linkedFormTemplateId()))
                ->url(fn () => FormSubmissionResource::getUrl('create', [
                    'form_template_id' => $this->linkedFormTemplateId(),
                ]))
                ->openUrlInNewTab(),
            DeleteAction::make(),
        ];
    }

    /**
     * The form template linked to this task's originating recurring
     * checklist template, if it was generated from one and a form was
     * configured on it.
     */
    private function linkedFormTemplateId(): ?int
    {
        if (! $this->record->source instanceof TaskChecklistTemplate) {
            return null;
        }

        return $this->record->source->form_template_id;
    }
}
