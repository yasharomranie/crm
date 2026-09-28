<?php

namespace App\Filament\Pages;

use App\Domain\Seo\GoogleOAuthService;
use App\Filament\Pages\Concerns\HasAccessControlledPage;
use App\Models\SeoGoogleSetting;
use BackedEnum;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

/**
 * A singleton settings screen (backs the single SeoGoogleSetting row, see
 * that model), not a CRUD resource - App\Domain\Seo\GoogleOAuthService reads
 * the Search Console OAuth client credentials from here rather than .env,
 * so they only ever need to be entered once, by an admin, from the panel -
 * no server/terminal access required. One OAuth app here is shared by every
 * SEO Project; each project still connects its own Google account/Search
 * Console property separately from its own edit page.
 */
class SeoGoogleSettings extends Page implements HasForms
{
    use HasAccessControlledPage;
    use InteractsWithForms;

    protected string $view = 'filament.pages.seo-google-settings';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedKey;

    protected static string|\UnitEnum|null $navigationGroup = 'مرکز سئو';

    protected static ?string $navigationLabel = 'تنظیمات اتصال گوگل';

    protected static ?string $title = 'تنظیمات اتصال گوگل (Search Console)';

    /**
     * @var array<string, mixed>
     */
    public array $data = [];

    public static function accessControlPermission(): string
    {
        return 'seo-google-settings.access';
    }

    public function mount(): void
    {
        $setting = SeoGoogleSetting::current();

        $this->form->fill([
            'client_id' => $setting->client_id,
            'client_secret' => $setting->client_secret,
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                Placeholder::make('redirect_uri')
                    ->label('آدرس بازگشت (Redirect URI)')
                    ->helperText('این آدرس را دقیقاً همین‌طور، در Google Cloud Console زیر «Authorized redirect URIs» ثبت کنید.')
                    ->content(fn (GoogleOAuthService $oauth) => $oauth->redirectUri()),
                TextInput::make('client_id')
                    ->label('Client ID')
                    ->maxLength(500),
                TextInput::make('client_secret')
                    ->label('Client Secret')
                    ->password()
                    ->revealable()
                    ->maxLength(500),
            ]);
    }

    public function save(): void
    {
        SeoGoogleSetting::current()->update($this->form->getState());

        Notification::make()->title('تنظیمات اتصال گوگل ذخیره شد.')->success()->send();
    }
}
