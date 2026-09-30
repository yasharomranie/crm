<?php

namespace App\Filament\Pages;

use App\Filament\Pages\Concerns\HasAccessControlledPage;
use App\Models\SeoRankTrackerSetting;
use BackedEnum;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

/**
 * A singleton settings screen (backs the single SeoRankTrackerSetting
 * row, see that model), not a CRUD resource. Despite the model/route name
 * (kept as-is to avoid a migration), this page's real remaining purpose is
 * the api_key field alone: App\Domain\Seo\PageSpeedInsightsClient reads it
 * for the (working) page-speed check in «ممیزی فنی سئو». The keyword rank
 * tracker this page used to configure is hidden from navigation
 * (App\Filament\Resources\SeoTrackedKeywords\SeoTrackedKeywordResource) -
 * Google's Custom Search JSON API "search the entire web" mode, which it
 * depended on, was permanently disabled by Google.
 */
class SeoRankTrackerSettings extends Page implements HasForms
{
    use HasAccessControlledPage;
    use InteractsWithForms;

    protected string $view = 'filament.pages.seo-rank-tracker-settings';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedKey;

    protected static string|\UnitEnum|null $navigationGroup = 'مرکز سئو';

    protected static ?string $navigationLabel = 'تنظیمات PageSpeed Insights';

    protected static ?string $title = 'تنظیمات PageSpeed Insights (تحلیل سرعت صفحه)';

    /**
     * @var array<string, mixed>
     */
    public array $data = [];

    public static function accessControlPermission(): string
    {
        return 'seo-rank-tracker-settings.access';
    }

    public function mount(): void
    {
        $setting = SeoRankTrackerSetting::current();

        $this->form->fill([
            'api_key' => $setting->api_key,
            'search_engine_id' => $setting->search_engine_id,
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                TextInput::make('api_key')
                    ->label('API Key')
                    ->password()
                    ->revealable()
                    ->maxLength(500)
                    ->helperText('از Google Cloud Console بسازید و مطمئن شوید PageSpeed Insights API روی همین پروژه‌ی گوگل فعال است - این کلید همان چیزی است که تحلیل سرعت صفحه در «ممیزی فنی سئو» استفاده می‌کند.'),
                TextInput::make('search_engine_id')
                    ->label('Search Engine ID (cx)')
                    ->maxLength(255)
                    ->helperText('این فیلد فقط برای ترکر کلمه کلیدی (که غیرفعال شده) بود؛ خودش هیچ اثری روی PageSpeed Insights ندارد، ولی همچنان باید پر باشد وگرنه سیستم این تنظیمات را «کامل نشده» تشخیص می‌دهد - هر مقداری کافی است.'),
            ]);
    }

    public function save(): void
    {
        SeoRankTrackerSetting::current()->update($this->form->getState());

        Notification::make()->title('تنظیمات ترکر کلمه کلیدی ذخیره شد.')->success()->send();
    }
}
