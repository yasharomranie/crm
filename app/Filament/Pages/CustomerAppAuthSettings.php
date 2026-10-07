<?php

namespace App\Filament\Pages;

use App\Filament\Pages\Concerns\HasAccessControlledPage;
use App\Models\CustomerAppAuthSetting;
use BackedEnum;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

/**
 * A singleton settings screen (backs the single CustomerAppAuthSetting
 * row) - lets an admin turn each of the وب‌اپ مشتریان's three sign-in
 * methods on or off without touching code. App\Http\Controllers\CustomerApp\AuthController
 * enforces every toggle on the server side too (not just hiding the
 * matching button/tab in resources/views/customer-app-auth.blade.php), so
 * a disabled method stays unreachable even by posting its route directly.
 * Turning "ورود با گوگل" on here still needs real OAuth credentials from
 * "تنظیمات ورود با گوگل" (CustomerAppGoogleSetting) - this toggle alone
 * can't make an unconfigured Google login work.
 */
class CustomerAppAuthSettings extends Page implements HasForms
{
    use HasAccessControlledPage;
    use InteractsWithForms;

    protected string $view = 'filament.pages.customer-app-auth-settings';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShieldCheck;

    protected static string|\UnitEnum|null $navigationGroup = 'وب‌اپ مشتریان';

    protected static ?string $navigationLabel = 'روش‌های ورود';

    protected static ?string $title = 'روش‌های ورود اپ مشتریان';

    /**
     * @var array<string, mixed>
     */
    public array $data = [];

    public static function accessControlPermission(): string
    {
        return 'customer-app-auth-settings.access';
    }

    public function mount(): void
    {
        $setting = CustomerAppAuthSetting::current();

        $this->form->fill([
            'otp_enabled' => $setting->otp_enabled,
            'password_enabled' => $setting->password_enabled,
            'google_enabled' => $setting->google_enabled,
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                Toggle::make('otp_enabled')
                    ->label('ورود با کد پیامکی (OTP)')
                    ->helperText('ورود/ثبت‌نام با شماره موبایل و کد یکبارمصرف پیامکی.'),
                Toggle::make('password_enabled')
                    ->label('ورود با رمز عبور')
                    ->helperText('ورود و ثبت‌نام با شماره موبایل یا ایمیل به‌همراه رمز عبور.'),
                Toggle::make('google_enabled')
                    ->label('ورود با گوگل')
                    ->helperText('علاوه بر این کلید، اتصال گوگل باید از «تنظیمات ورود با گوگل» هم پیکربندی شده باشد.'),
            ]);
    }

    public function save(): void
    {
        CustomerAppAuthSetting::current()->update($this->form->getState());

        Notification::make()->title('تنظیمات روش‌های ورود ذخیره شد.')->success()->send();
    }
}
