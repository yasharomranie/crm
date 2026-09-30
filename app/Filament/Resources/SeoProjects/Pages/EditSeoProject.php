<?php

namespace App\Filament\Resources\SeoProjects\Pages;

use App\Domain\Seo\SearchConsoleClient;
use App\Enums\SeoConnectionStatus;
use App\Exceptions\SeoGoogleException;
use App\Filament\Resources\SeoProjects\SeoProjectResource;
use App\Jobs\AnalyzeSeoProjectJob;
use App\Jobs\ClassifySeoKeywordsJob;
use App\Jobs\DiscoverSeoKeywordsJob;
use App\Jobs\SyncSearchConsoleDataJob;
use App\Models\SeoProject;
use App\Models\SeoQueryMetric;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Icons\Heroicon;

class EditSeoProject extends EditRecord
{
    protected static string $resource = SeoProjectResource::class;

    /**
     * GoogleOAuthController redirects back here with a plain session flash
     * (it has no Livewire component of its own to send a Filament
     * Notification from) - this is what actually surfaces that message.
     */
    public function mount(int|string $record): void
    {
        parent::mount($record);

        if (session()->has('error')) {
            Notification::make()->title(session('error'))->danger()->send();
        }

        if (session()->has('success')) {
            Notification::make()->title(session('success'))->success()->send();
        }
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('connectGoogle')
                ->label('اتصال Google Search Console')
                ->icon(Heroicon::OutlinedLink)
                ->visible(fn (SeoProject $record) => $record->googleConnection?->status !== SeoConnectionStatus::Connected)
                ->url(fn (SeoProject $record) => route('seo.google.redirect', $record)),

            Action::make('pickGscSite')
                ->label('انتخاب ملک Search Console')
                ->icon(Heroicon::OutlinedGlobeAlt)
                ->visible(fn (SeoProject $record) => $record->googleConnection?->status === SeoConnectionStatus::Connected)
                ->schema(fn (SeoProject $record) => [
                    Select::make('gsc_site_url')
                        ->label('ملک (Property)')
                        ->options(function () use ($record) {
                            try {
                                $sites = (new SearchConsoleClient($record->googleConnection->access_token))->listSites();

                                return collect($sites)->pluck('siteUrl', 'siteUrl')->all();
                            } catch (SeoGoogleException $exception) {
                                Notification::make()
                                    ->title('دریافت لیست ملک‌ها از گوگل ناموفق بود')
                                    ->body($exception->getMessage())
                                    ->danger()
                                    ->send();

                                return [];
                            }
                        })
                        ->searchable()
                        ->required()
                        ->default($record->googleConnection?->gsc_site_url),
                ])
                ->action(function (array $data, SeoProject $record) {
                    $record->googleConnection->update(['gsc_site_url' => $data['gsc_site_url']]);

                    Notification::make()->title('ملک انتخاب‌شده ذخیره شد.')->success()->send();
                }),

            Action::make('syncNow')
                ->label('همگام‌سازی الان')
                ->icon(Heroicon::OutlinedArrowPath)
                ->visible(fn (SeoProject $record) => $record->googleConnection?->status === SeoConnectionStatus::Connected
                    && filled($record->googleConnection?->gsc_site_url))
                ->action(function (SeoProject $record) {
                    SyncSearchConsoleDataJob::dispatch($record->id);

                    Notification::make()
                        ->title('همگام‌سازی در صف اجرا قرار گرفت.')
                        ->body('چند دقیقه‌ی دیگر داده‌های جدید در گزارش‌ها قابل مشاهده است.')
                        ->success()
                        ->send();
                }),

            Action::make('analyzeNow')
                ->label('تحلیل الان')
                ->icon(Heroicon::OutlinedSparkles)
                ->tooltip('پروفایل کلمات کلیدی را بازسازی و فرصت‌های سئو را دوباره اسکن می‌کند - بدون تماس جدید با Search Console.')
                ->visible(fn (SeoProject $record) => SeoQueryMetric::where('seo_project_id', $record->id)->exists())
                ->action(function (SeoProject $record) {
                    AnalyzeSeoProjectJob::dispatch($record->id);

                    Notification::make()
                        ->title('تحلیل در صف اجرا قرار گرفت.')
                        ->success()
                        ->send();
                }),

            Action::make('classifyNow')
                ->label('طبقه‌بندی هوشمند کلمات')
                ->icon(Heroicon::OutlinedTag)
                ->tooltip('Intent، ارزش تجاری و خوشه‌ی موضوعی کلمات کلیدی هنوز طبقه‌بندی‌نشده را با هوش مصنوعی مشخص می‌کند.')
                ->visible(fn (SeoProject $record) => SeoQueryMetric::where('seo_project_id', $record->id)->exists())
                ->action(function (SeoProject $record) {
                    ClassifySeoKeywordsJob::dispatch($record->id);

                    Notification::make()
                        ->title('طبقه‌بندی هوشمند در صف اجرا قرار گرفت.')
                        ->success()
                        ->send();
                }),

            Action::make('discoverKeywords')
                ->label('کشف کلمات کلیدی جدید')
                ->icon(Heroicon::OutlinedLightBulb)
                ->tooltip('با هوش مصنوعی و بر اساس اطلاعات کسب‌وکار پروژه، ایده‌ی کلمات کلیدی جدید تولید می‌کند - این‌ها حدس هستند، نه داده‌ی واقعی، و باید توسط شما تأیید شوند.')
                ->schema([
                    TextInput::make('count')
                        ->label('تعداد پیشنهاد')
                        ->numeric()
                        ->default(20)
                        ->minValue(5)
                        ->maxValue(50)
                        ->required(),
                ])
                ->action(function (array $data, SeoProject $record) {
                    DiscoverSeoKeywordsJob::dispatch($record->id, (int) $data['count']);

                    if (blank($record->real_services)) {
                        Notification::make()
                            ->title('فیلد «خدمات و محصولات واقعی» این پروژه خالی است.')
                            ->body('پیشنهادهای هوش مصنوعی ممکن است با کسب‌وکار واقعی شما همخوانی نداشته باشند - آن را از بخش «اطلاعات کلی» همین صفحه پر کنید.')
                            ->warning()
                            ->send();
                    }

                    Notification::make()
                        ->title('کشف کلمات کلیدی جدید در صف اجرا قرار گرفت.')
                        ->body('نتایج در بخش «پیشنهادهای کلمات کلیدی» قابل بررسی و تأیید خواهند بود.')
                        ->success()
                        ->send();
                }),

            DeleteAction::make(),
        ];
    }
}
