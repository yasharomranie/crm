<?php

namespace App\Filament\Pages;

use App\Domain\Seo\TelegramNotifier;
use App\Filament\Pages\Concerns\HasAccessControlledPage;
use App\Models\SeoAlertSetting;
use App\Models\User;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

/**
 * A singleton settings screen (backs the single SeoAlertSetting row) -
 * decides whether App\Domain\Seo\SeoAlertService pages anyone at all, by
 * how much a rank has to drop, which channels fire (mirrors
 * EventReminder's notify_push/notify_sms/notify_email flags, just app-wide
 * instead of per-reminder), and who receives it.
 */
class SeoAlertSettings extends Page implements HasForms
{
    use HasAccessControlledPage;
    use InteractsWithForms;

    protected string $view = 'filament.pages.seo-alert-settings';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBellAlert;

    protected static string|\UnitEnum|null $navigationGroup = 'مرکز سئو';

    protected static ?string $navigationLabel = 'تنظیمات هشدار سئو';

    protected static ?string $title = 'تنظیمات هشدار سئو';

    /**
     * @var array<string, mixed>
     */
    public array $data = [];

    public static function accessControlPermission(): string
    {
        return 'seo-alert-settings.access';
    }

    public function mount(): void
    {
        $setting = SeoAlertSetting::current();

        $this->form->fill([
            'is_enabled' => $setting->is_enabled,
            'rank_drop_threshold' => $setting->rank_drop_threshold,
            'notify_email' => $setting->notify_email,
            'notify_sms' => $setting->notify_sms,
            'notify_push' => $setting->notify_push,
            'notify_telegram' => $setting->notify_telegram,
            'telegram_chat_id' => $setting->telegram_chat_id,
            'telegram_api_base' => $setting->telegram_api_base,
            'recipient_user_ids' => $setting->recipient_user_ids ?? [],
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                Toggle::make('is_enabled')
                    ->label('فعال بودن هشدارها')
                    ->helperText('در صورت غیرفعال بودن، هیچ هشداری برای افت رتبه یا فرصت مهم ارسال نمی‌شود.'),
                TextInput::make('rank_drop_threshold')
                    ->label('آستانهٔ افت رتبه')
                    ->numeric()
                    ->minValue(1)
                    ->required()
                    ->helperText('اگر رتبهٔ یک کلمهٔ کلیدی به این تعداد یا بیشتر افت کند (یا کاملاً از ۱۰ نتیجهٔ اول خارج شود)، هشدار ارسال می‌شود.'),
                Toggle::make('notify_push')
                    ->label('اعلان فوری (Web Push)'),
                Toggle::make('notify_telegram')
                    ->label('تلگرام')
                    ->helperText('هشدار افت رتبه، فرصت مهم، قطع شدن سایت و نزدیک شدن انقضای SSL به یک گفتگوی تلگرام می‌رود (بدون نیاز به انتخاب کاربر).'),
                TextInput::make('telegram_bot_token')
                    ->label('توکن بات تلگرام')
                    ->password()
                    ->revealable()
                    ->placeholder(fn () => filled(SeoAlertSetting::current()->telegram_bot_token) ? 'ذخیره شده؛ برای تغییر، توکن تازه را بنویسید' : '123456:ABC...')
                    ->dehydrated(fn (?string $state) => filled($state))
                    ->helperText('از @BotFather در تلگرام یک بات بسازید و توکنش را اینجا بگذارید.'),
                TextInput::make('telegram_chat_id')
                    ->label('شناسه‌ی گفتگو (Chat ID)')
                    ->helperText('بات را به گروه یا کانال اضافه کنید (یا به خود بات پیام بدهید) و شناسه‌ی گفتگو را اینجا بنویسید.'),
                TextInput::make('telegram_api_base')
                    ->label('آدرس API تلگرام (اختیاری)')
                    ->url()
                    ->placeholder('https://api.telegram.org')
                    ->helperText('اگر سرور شما به تلگرام دسترسی ندارد، آدرس یک رله‌ی (پروکسی) تلگرام را اینجا بگذارید.'),
                Toggle::make('notify_sms')
                    ->label('پیامک'),
                Toggle::make('notify_email')
                    ->label('ایمیل'),
                CheckboxList::make('recipient_user_ids')
                    ->label('کاربران دریافت‌کنندهٔ هشدار')
                    ->options(fn () => User::pluck('name', 'id'))
                    ->searchable()
                    ->columns(2)
                    ->helperText('هشدار فرصت مهم سئو فقط برای فرصت‌های با اولویت «بالا» ارسال می‌شود.'),
            ]);
    }

    /**
     * @return array<int, Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('sendTelegramTest')
                ->label('ارسال پیام آزمایشی به تلگرام')
                ->action(function () {
                    $setting = SeoAlertSetting::current();

                    $sent = $setting->telegramIsUsable()
                        && app(TelegramNotifier::class)->send('✅ پیام آزمایشی مرکز سئو: اتصال تلگرام درست کار می‌کند.', $setting);

                    $sent
                        ? Notification::make()->title('پیام آزمایشی ارسال شد.')->success()->send()
                        : Notification::make()->title('ارسال نشد.')->body('تلگرام را فعال کنید، توکن و شناسه‌ی گفتگو را ذخیره کنید و اگر سرور به تلگرام دسترسی ندارد آدرس رله بدهید.')->danger()->send();
                }),
        ];
    }

    public function save(): void
    {
        SeoAlertSetting::current()->update($this->form->getState());

        Notification::make()->title('تنظیمات هشدار سئو ذخیره شد.')->success()->send();
    }
}
