<?php

namespace App\Filament\Pages;

use App\Filament\Pages\Concerns\HasAccessControlledPage;
use App\Models\SmsSetting;
use BackedEnum;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

/**
 * A singleton settings screen (backs the single SmsSetting row, see that
 * model), not a CRUD resource - every part of the system that sends SMS
 * (starting with App\Support\SmsGateway, used by event reminders) reads
 * its sms.ir credentials from here rather than from .env, so they only
 * ever need to be entered once, by an admin, from the panel.
 */
class SmsSettings extends Page implements HasForms
{
    use HasAccessControlledPage;
    use InteractsWithForms;

    protected string $view = 'filament.pages.sms-settings';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChatBubbleLeftRight;

    protected static string|\UnitEnum|null $navigationGroup = 'زیرساخت سازمانی';

    protected static ?string $navigationLabel = 'تنظیمات پیامک';

    protected static ?string $title = 'تنظیمات پیامک (sms.ir)';

    /**
     * @var array<string, mixed>
     */
    public array $data = [];

    public static function accessControlPermission(): string
    {
        return 'sms-settings.access';
    }

    public function mount(): void
    {
        $setting = SmsSetting::current();

        $this->form->fill([
            'is_enabled' => $setting->is_enabled,
            'api_key' => $setting->api_key,
            'line_number' => $setting->line_number,
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                Toggle::make('is_enabled')
                    ->label('فعال بودن ارسال پیامک')
                    ->helperText('تا وقتی فعال نباشد و کلید API/شماره خط تنظیم نشود، هیچ پیامکی از سامانه ارسال نمی‌شود.'),
                TextInput::make('api_key')
                    ->label('کلید API سامانه پیامکی (sms.ir)')
                    ->password()
                    ->revealable()
                    ->maxLength(500),
                TextInput::make('line_number')
                    ->label('شماره خط ارسال')
                    ->maxLength(50),
            ]);
    }

    public function save(): void
    {
        SmsSetting::current()->update($this->form->getState());

        Notification::make()->title('تنظیمات پیامک ذخیره شد.')->success()->send();
    }
}
