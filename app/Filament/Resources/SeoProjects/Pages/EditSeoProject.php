<?php

namespace App\Filament\Resources\SeoProjects\Pages;

use App\Domain\Seo\GoogleAnalyticsClient;
use App\Domain\Seo\GoogleOAuthService;
use App\Domain\Seo\SearchConsoleClient;
use App\Domain\Seo\WpConnector\WpPairingService;
use App\Enums\SeoConnectionStatus;
use App\Exceptions\SeoGoogleException;
use App\Filament\Resources\SeoProjects\SeoProjectResource;
use App\Jobs\AnalyzeCompetitorContentGapsJob;
use App\Jobs\AnalyzeSeoProjectJob;
use App\Jobs\ClassifySeoKeywordsJob;
use App\Jobs\DiscoverSeoKeywordsJob;
use App\Jobs\SyncGoogleAnalyticsJob;
use App\Jobs\SyncSearchConsoleDataJob;
use App\Jobs\SyncSitemapDataJob;
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

            Action::make('reconnectForAnalytics')
                ->label('اتصال مجدد برای Google Analytics')
                ->icon(Heroicon::OutlinedChartBar)
                ->tooltip('این اتصال هنوز اجازه‌ی خواندن Google Analytics را ندارد؛ یک بار دیگر حساب گوگل را وصل کنید و گزینه‌ی Analytics را تیک بزنید.')
                ->visible(fn (SeoProject $record) => $record->googleConnection?->status === SeoConnectionStatus::Connected
                    && ! $record->googleConnection->canReadAnalytics())
                ->url(fn (SeoProject $record) => route('seo.google.redirect', $record)),

            Action::make('pickGa4Property')
                ->label('انتخاب ویژگی Google Analytics (GA4)')
                ->icon(Heroicon::OutlinedChartBar)
                ->visible(fn (SeoProject $record) => $record->googleConnection?->status === SeoConnectionStatus::Connected
                    && $record->googleConnection->canReadAnalytics())
                ->schema(fn (SeoProject $record) => [
                    Select::make('ga4_property_id')
                        ->label('ویژگی (Property)')
                        ->options(function () use ($record) {
                            try {
                                return (new GoogleAnalyticsClient(app(GoogleOAuthService::class)->freshAccessTokenFor($record->googleConnection)))->listProperties();
                            } catch (SeoGoogleException $exception) {
                                Notification::make()
                                    ->title('دریافت فهرست ویژگی‌ها از Google Analytics ناموفق بود')
                                    ->body($exception->getMessage())
                                    ->danger()
                                    ->send();

                                return [];
                            }
                        })
                        ->searchable()
                        ->required()
                        ->default($record->googleConnection?->ga4_property_id),
                ])
                ->action(function (array $data, SeoProject $record) {
                    $record->googleConnection->update(['ga4_property_id' => $data['ga4_property_id'], 'ga_last_error' => null]);
                    SyncGoogleAnalyticsJob::dispatch($record->id);

                    Notification::make()->title('ویژگی ذخیره شد و دریافت آمار در صف قرار گرفت.')->success()->send();
                }),

            Action::make('syncAnalyticsNow')
                ->label('همگام‌سازی Analytics الان')
                ->icon(Heroicon::OutlinedArrowPath)
                ->visible(fn (SeoProject $record) => filled($record->googleConnection?->ga4_property_id))
                ->action(function (SeoProject $record) {
                    SyncGoogleAnalyticsJob::dispatch($record->id);

                    Notification::make()->title('دریافت آمار Analytics در صف اجرا قرار گرفت.')->success()->send();
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

            Action::make('syncSitemapNow')
                ->label('همگام‌سازی Sitemap الان')
                ->icon(Heroicon::OutlinedDocumentText)
                ->visible(fn (SeoProject $record) => filled($record->sitemap_url))
                ->action(function (SeoProject $record) {
                    SyncSitemapDataJob::dispatch($record->id);

                    Notification::make()
                        ->title('همگام‌سازی Sitemap در صف اجرا قرار گرفت.')
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

            Action::make('analyzeCompetitorGaps')
                ->label('تحلیل شکاف رقبا')
                ->icon(Heroicon::OutlinedUserGroup)
                ->tooltip('sitemap هر رقیب را می‌خزد و موضوعاتی را که چند رقیب پوشش داده‌اند ولی شما محتوایی برایشان ندارید، به‌عنوان پیشنهاد کلمه کلیدی اضافه می‌کند.')
                ->visible(fn (SeoProject $record) => $record->competitors()->exists())
                ->action(function (SeoProject $record) {
                    AnalyzeCompetitorContentGapsJob::dispatch($record->id);

                    Notification::make()
                        ->title('تحلیل شکاف رقبا در صف اجرا قرار گرفت.')
                        ->body('نتایج در بخش «پیشنهادهای کلمات کلیدی» قابل بررسی و تأیید خواهند بود.')
                        ->success()
                        ->send();
                }),

            Action::make('connectWordPress')
                ->label('اتصال وردپرس')
                ->icon(Heroicon::OutlinedPuzzlePiece)
                ->tooltip('یک کد اتصال یک‌بارمصرف می‌سازد؛ آن را در پلاگین اتصال CRM روی سایت وردپرسی وارد کنید.')
                ->action(function (SeoProject $record, WpPairingService $pairing) {
                    $code = $pairing->issueCode($record);

                    Notification::make()
                        ->title("کد اتصال: {$code}")
                        ->body('این کد ۲۴ ساعت و فقط یک بار معتبر است. در پلاگین، آدرس CRM را «'.url('/').'» و همین کد را وارد کنید.')
                        ->success()
                        ->persistent()
                        ->send();
                }),

            Action::make('disconnectWordPress')
                ->label('قطع اتصال وردپرس')
                ->icon(Heroicon::OutlinedXCircle)
                ->color('danger')
                ->requiresConfirmation()
                ->modalDescription('پلاگین دیگر نمی‌تواند چیزی بفرستد تا دوباره با کد تازه وصل شود. اطلاعاتی که تا حالا دریافت شده می‌ماند.')
                ->visible(fn (SeoProject $record) => $record->wpSite?->isConnected() ?? false)
                ->action(function (SeoProject $record, WpPairingService $pairing) {
                    $pairing->disconnect($record->wpSite);

                    Notification::make()->title('اتصال وردپرس قطع شد.')->success()->send();
                }),

            DeleteAction::make(),
        ];
    }
}
