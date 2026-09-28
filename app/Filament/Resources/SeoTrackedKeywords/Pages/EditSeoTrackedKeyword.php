<?php

namespace App\Filament\Resources\SeoTrackedKeywords\Pages;

use App\Filament\Resources\SeoTrackedKeywords\SeoTrackedKeywordResource;
use App\Models\SeoTrackedKeyword;
use Filament\Actions\DeleteAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditSeoTrackedKeyword extends EditRecord
{
    protected static string $resource = SeoTrackedKeywordResource::class;

    private const MAX_ACTIVE_PER_PROJECT = 50;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }

    /**
     * Same ceiling as CreateSeoTrackedKeyword - re-activating a keyword
     * (or moving it to a different, already-full project) must not be
     * able to sneak past the 50-active-per-project cap either.
     *
     * Compares against getOriginal(), not the live attribute: the
     * relationship-backed "seo_project_id" Select mutates $record's
     * in-memory attributes as a side effect of $this->form->getState()
     * (called by EditRecord::save() before handleRecordUpdate runs), so
     * by the time we get here $record->seo_project_id already reads as
     * the NEW value and would always look "unchanged".
     */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        $wasActive = (bool) $record->getOriginal('is_active');
        $originalProjectId = $record->getOriginal('seo_project_id');

        $reactivating = ($data['is_active'] ?? false) && ! $wasActive;
        $changingProject = $data['seo_project_id'] !== $originalProjectId;
        $wantsActive = $data['is_active'] ?? false;

        if ($wantsActive && ($reactivating || $changingProject)) {
            $activeCount = SeoTrackedKeyword::where('seo_project_id', $data['seo_project_id'])
                ->where('is_active', true)
                ->where('id', '!=', $record->id)
                ->count();

            if ($activeCount >= self::MAX_ACTIVE_PER_PROJECT) {
                Notification::make()
                    ->title('سقف کلمات کلیدی فعال پر شده است')
                    ->body('هر پروژه حداکثر ۵۰ کلمه کلیدی فعال می‌تواند داشته باشد.')
                    ->danger()
                    ->send();

                $this->halt();
            }
        }

        $record->update($data);

        return $record;
    }
}
