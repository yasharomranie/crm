<?php

namespace App\Filament\Resources\FormTemplates\Pages;

use App\Filament\Resources\FormTemplates\FormTemplateResource;
use App\Models\FormTemplate;
use Filament\Actions\DeleteAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditFormTemplate extends EditRecord
{
    protected static string $resource = FormTemplateResource::class;

    /**
     * form_submissions.form_template_id is restrictOnDelete() at the
     * database level now, so a template with real submissions can never
     * actually be deleted out from under its data - this is the friendly,
     * pre-checked version of that same rule, so an admin sees why up front
     * instead of a raw database error after confirming.
     */
    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()
                ->action(function (FormTemplate $record) {
                    if ($record->submissions()->exists()) {
                        Notification::make()
                            ->title('این فرم قابل حذف نیست')
                            ->body('برای این قالب سابمیشن ثبت شده وجود دارد؛ برای حفظ اطلاعات ثبت‌شده، حذف قالب مسدود شده است. در صورت نیاز، فرم را غیرفعال کنید.')
                            ->danger()
                            ->send();

                        return;
                    }

                    $record->delete();

                    $this->redirect(static::getResource()::getUrl('index'));
                }),
        ];
    }
}
