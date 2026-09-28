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
 * row, see that model), not a CRUD resource -
 * App\Domain\Seo\GoogleCustomSearchClient reads the Custom Search JSON
 * API credentials from here rather than .env, so they only ever need to
 * be entered once, by an admin, from the panel - no server/terminal
 * access required.
 */
class SeoRankTrackerSettings extends Page implements HasForms
{
    use HasAccessControlledPage;
    use InteractsWithForms;

    protected string $view = 'filament.pages.seo-rank-tracker-settings';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedKey;

    protected static string|\UnitEnum|null $navigationGroup = 'مرکز سئو';

    protected static ?string $navigationLabel = 'تنظیمات ترکر کلمه کلیدی';

    protected static ?string $title = 'تنظیمات ترکر کلمه کلیدی (Google Custom Search)';

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
                    ->helperText('از Google Cloud Console، پس از فعال‌سازی Custom Search API، از بخش Credentials بسازید. برای فعال شدن تحلیل سرعت صفحه در «ممیزی فنی سئو»، PageSpeed Insights API را هم روی همین پروژه‌ی گوگل فعال کنید - نیازی به کلید جداگانه نیست.'),
                TextInput::make('search_engine_id')
                    ->label('Search Engine ID (cx)')
                    ->maxLength(255)
                    ->helperText('از programmablesearchengine.google.com، یک موتور جستجوی جدید با گزینه‌ی «جستجو در کل وب» بسازید و این مقدار را از تنظیمات آن کپی کنید.'),
            ]);
    }

    public function save(): void
    {
        SeoRankTrackerSetting::current()->update($this->form->getState());

        Notification::make()->title('تنظیمات ترکر کلمه کلیدی ذخیره شد.')->success()->send();
    }
}
