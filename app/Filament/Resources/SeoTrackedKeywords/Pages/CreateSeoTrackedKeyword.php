<?php

namespace App\Filament\Resources\SeoTrackedKeywords\Pages;

use App\Filament\Resources\SeoTrackedKeywords\SeoTrackedKeywordResource;
use App\Models\SeoTrackedKeyword;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateSeoTrackedKeyword extends CreateRecord
{
    protected static string $resource = SeoTrackedKeywordResource::class;

    private const MAX_ACTIVE_PER_PROJECT = 50;

    /**
     * A worker on the free Google Custom Search quota (100/day, shared
     * across the whole app) needs a hard ceiling per project or one
     * project alone could exhaust it - see GoogleCustomSearchClient's own
     * doc comment.
     */
    protected function handleRecordCreation(array $data): Model
    {
        $isActive = $data['is_active'] ?? true;

        if ($isActive) {
            $activeCount = SeoTrackedKeyword::where('seo_project_id', $data['seo_project_id'])
                ->where('is_active', true)
                ->count();

            if ($activeCount >= self::MAX_ACTIVE_PER_PROJECT) {
                Notification::make()
                    ->title('سقف کلمات کلیدی فعال پر شده است')
                    ->body('هر پروژه حداکثر ۵۰ کلمه کلیدی فعال می‌تواند داشته باشد. یکی را غیرفعال یا حذف کنید و دوباره تلاش کنید.')
                    ->danger()
                    ->send();

                $this->halt();
            }
        }

        return static::getModel()::create($data);
    }
}
