<?php

namespace App\Filament\Pages;

use App\Domain\CustomerApp\CustomerGoogleOAuthService;
use App\Filament\Pages\Concerns\HasAccessControlledPage;
use App\Models\CustomerAppGoogleSetting;
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
 * A singleton settings screen (backs the single CustomerAppGoogleSetting
 * row), mirroring SeoGoogleSettings - lets an admin enter the OAuth client
 * credentials "ورود با گوگل" in the وب‌اپ مشتریان uses, from a dedicated
 * Google Cloud OAuth app kept separate from the one SEO's Search Console
 * integration uses (see CustomerAppGoogleSetting's own doc comment).
 */
class CustomerAppGoogleSettings extends Page implements HasForms
{
    use HasAccessControlledPage;
    use InteractsWithForms;

    protected string $view = 'filament.pages.customer-app-google-settings';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedKey;

    protected static string|\UnitEnum|null $navigationGroup = 'وب‌اپ مشتریان';

    protected static ?string $navigationLabel = 'ورود با گوگل';

    protected static ?string $title = 'تنظیمات ورود با گوگل (اپ مشتریان)';

    /**
     * @var array<string, mixed>
     */
    public array $data = [];

    public static function accessControlPermission(): string
    {
        return 'customer-app-google-settings.access';
    }

    public function mount(): void
    {
        $setting = CustomerAppGoogleSetting::current();

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
                    ->content(fn (CustomerGoogleOAuthService $oauth) => $oauth->redirectUri()),
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
        CustomerAppGoogleSetting::current()->update($this->form->getState());

        Notification::make()->title('تنظیمات ورود با گوگل ذخیره شد.')->success()->send();
    }
}
