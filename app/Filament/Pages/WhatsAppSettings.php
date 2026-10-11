<?php

namespace App\Filament\Pages;

use App\Filament\Pages\Concerns\HasAccessControlledPage;
use App\Models\AiAssistantProfile;
use App\Models\WhatsAppBusinessSetting;
use BackedEnum;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

/**
 * A singleton settings screen (backs the single WhatsAppBusinessSetting
 * row) for the WhatsApp Cloud API channel - lets a custom AiAssistantProfile
 * answer real incoming WhatsApp messages, not just chats inside the admin
 * panel (WhatsAppWebhookController, WhatsAppInboundMessageHandler). Entirely
 * inert (isUsable() stays false, so the webhook rejects everything) until
 * an admin has a real Meta WhatsApp Business API account and enters its
 * credentials here - this page itself has no dependency on one existing.
 */
class WhatsAppSettings extends Page implements HasForms
{
    use HasAccessControlledPage;
    use InteractsWithForms;

    protected string $view = 'filament.pages.whats-app-settings';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChatBubbleLeftEllipsis;

    protected static string|\UnitEnum|null $navigationGroup = 'دستیار هوشمند مدیریتی';

    protected static ?string $navigationLabel = 'اتصال واتس‌اپ';

    protected static ?string $title = 'اتصال دستیار به واتس‌اپ کسب‌وکاری';

    /**
     * @var array<string, mixed>
     */
    public array $data = [];

    public static function accessControlPermission(): string
    {
        return 'whatsapp-settings.access';
    }

    public function mount(): void
    {
        $setting = WhatsAppBusinessSetting::current();

        $this->form->fill([
            'is_enabled' => $setting->is_enabled,
            'phone_number_id' => $setting->phone_number_id,
            'access_token' => $setting->access_token,
            'verify_token' => $setting->verify_token,
            'ai_assistant_profile_id' => $setting->ai_assistant_profile_id,
        ]);
    }

    public function webhookUrl(): string
    {
        return url('/api/v1/whatsapp/webhook');
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                Toggle::make('is_enabled')
                    ->label('فعال بودن اتصال واتس‌اپ')
                    ->helperText('تا وقتی فعال نباشد و همهٔ فیلدهای زیر تنظیم نشوند، هیچ پیامی از واتس‌اپ پاسخ داده نمی‌شود.'),
                Select::make('ai_assistant_profile_id')
                    ->label('دستیاری که پاسخ‌گوی واتس‌اپ باشد')
                    ->options(fn () => AiAssistantProfile::query()->pluck('name', 'id'))
                    ->searchable()
                    ->helperText('این دستیار بدون هیچ ابزاری (نه مشورت، نه خواندن وب) فقط بر اساس پرامت و دانش‌نامهٔ خودش به پیام‌های واتس‌اپ پاسخ می‌دهد.'),
                TextInput::make('phone_number_id')
                    ->label('شناسهٔ شمارهٔ تلفن (Phone Number ID)')
                    ->maxLength(255),
                TextInput::make('access_token')
                    ->label('توکن دسترسی (Access Token)')
                    ->password()
                    ->revealable()
                    ->maxLength(2000),
                TextInput::make('verify_token')
                    ->label('توکن تأیید وب‌هوک (Verify Token - خودتان یک رشتهٔ دلخواه انتخاب کنید)')
                    ->maxLength(255),
            ]);
    }

    public function save(): void
    {
        WhatsAppBusinessSetting::current()->update($this->form->getState());

        Notification::make()->title('تنظیمات واتس‌اپ ذخیره شد.')->success()->send();
    }
}
