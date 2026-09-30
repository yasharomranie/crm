<?php

namespace App\Filament\Resources\SeoKeywordSuggestions\Pages;

use App\Filament\Resources\SeoKeywordSuggestions\SeoKeywordSuggestionResource;
use App\Jobs\DiscoverSeoKeywordsJob;
use App\Models\SeoProject;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ManageRecords;
use Filament\Support\Icons\Heroicon;

class ManageSeoKeywordSuggestions extends ManageRecords
{
    protected static string $resource = SeoKeywordSuggestionResource::class;

    /**
     * A shortcut for App\Domain\Seo\AiKeywordDiscoveryService that doesn't
     * require leaving this review list to go find the project's own edit
     * page (see App\Filament\Resources\SeoProjects\Pages\EditSeoProject,
     * which still has the same action for when a project is already open)
     * - this list spans every project, so it needs its own project picker
     * first instead of an implicit "current record".
     */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('discoverKeywords')
                ->label('کشف کلمات کلیدی جدید')
                ->icon(Heroicon::OutlinedLightBulb)
                ->tooltip('با هوش مصنوعی و بر اساس اطلاعات کسب‌وکار پروژه، ایده‌ی کلمات کلیدی جدید تولید می‌کند - این‌ها حدس هستند، نه داده‌ی واقعی، و باید توسط شما تأیید شوند.')
                ->schema([
                    Select::make('seo_project_id')
                        ->label('پروژه')
                        ->options(fn () => SeoProject::pluck('name', 'id'))
                        ->searchable()
                        ->required(),
                    TextInput::make('count')
                        ->label('تعداد پیشنهاد')
                        ->numeric()
                        ->default(20)
                        ->minValue(5)
                        ->maxValue(50)
                        ->required(),
                ])
                ->action(function (array $data): void {
                    DiscoverSeoKeywordsJob::dispatch((int) $data['seo_project_id'], (int) $data['count']);

                    Notification::make()
                        ->title('کشف کلمات کلیدی جدید در صف اجرا قرار گرفت.')
                        ->body('نتایج به‌محض آماده شدن در همین جدول، با وضعیت «جدید»، قابل بررسی و تأیید خواهند بود.')
                        ->success()
                        ->send();
                }),
        ];
    }
}
