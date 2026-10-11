<?php

namespace App\Filament\Resources\SeoKeywordSuggestions\Pages;

use App\Filament\Resources\SeoKeywordSuggestions\SeoKeywordSuggestionResource;
use App\Jobs\DiscoverSeoKeywordsJob;
use App\Models\SeoKeywordSuggestion;
use App\Models\SeoProject;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ManageRecords;
use Filament\Support\Icons\Heroicon;
use Symfony\Component\HttpFoundation\StreamedResponse;

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
            Action::make('exportKeywordSuggestions')
                ->label('خروجی اکسل')
                ->icon(Heroicon::OutlinedArrowDownTray)
                ->color('gray')
                ->tooltip('خروجی اکسل از پیشنهادهای کلمات کلیدی، مطابق با فیلترها و جست‌وجوی فعلی جدول - برای تحویل به نویسنده‌ی محتوا.')
                ->action(fn (): StreamedResponse => $this->exportKeywordSuggestionsToExcel()),
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

                    if (blank(SeoProject::find($data['seo_project_id'])?->real_services)) {
                        Notification::make()
                            ->title('فیلد «خدمات و محصولات واقعی» این پروژه خالی است.')
                            ->body('پیشنهادهای هوش مصنوعی ممکن است با کسب‌وکار واقعی شما همخوانی نداشته باشند - از صفحه‌ی همان پروژه، بخش «اطلاعات کلی»، آن را پر کنید.')
                            ->warning()
                            ->send();
                    }

                    Notification::make()
                        ->title('کشف کلمات کلیدی جدید در صف اجرا قرار گرفت.')
                        ->body('نتایج به‌محض آماده شدن در همین جدول، با وضعیت «جدید»، قابل بررسی و تأیید خواهند بود.')
                        ->success()
                        ->send();
                }),
        ];
    }

    /**
     * A plain CSV (UTF-8 + BOM, so Excel reads the Persian text correctly)
     * built straight from getTableQueryForExport() - the same filtered,
     * searched, sorted query Filament's own ExportAction uses - rather
     * than the full async Exporter/queue pipeline, since this list is
     * small and the writer just needs the file immediately.
     */
    private function exportKeywordSuggestionsToExcel(): StreamedResponse
    {
        return response()->streamDownload(function (): void {
            $handle = fopen('php://output', 'w');
            fwrite($handle, "\xEF\xBB\xBF");

            fputcsv($handle, [
                'پروژه', 'کلمه کلیدی پیشنهادی', 'Intent', 'خوشه موضوعی',
                'نوع محتوای پیشنهادی', 'دلیل', 'وضعیت',
            ]);

            $this->getTableQueryForExport()
                ->with(['project', 'topicCluster'])
                ->each(function (SeoKeywordSuggestion $suggestion) use ($handle): void {
                    fputcsv($handle, [
                        $suggestion->project?->name,
                        $suggestion->keyword,
                        $suggestion->intent?->getLabel(),
                        $suggestion->topicCluster?->name,
                        $suggestion->suggested_content_type,
                        $suggestion->reason,
                        $suggestion->status->getLabel(),
                    ]);
                });

            fclose($handle);
        }, 'پیشنهادهای-کلمات-کلیدی.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }
}
