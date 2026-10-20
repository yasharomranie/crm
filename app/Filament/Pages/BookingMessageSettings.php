<?php

namespace App\Filament\Pages;

use App\Filament\Pages\Concerns\HasAccessControlledPage;
use App\Models\BookingMessageSetting;
use BackedEnum;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

/**
 * A singleton settings screen (backs the single BookingMessageSetting row):
 * which automatic SMS the guest receives around a stay and what each says.
 * Needs the SMS service («تنظیمات پیامک») to be switched on.
 */
class BookingMessageSettings extends Page implements HasForms
{
    use HasAccessControlledPage;
    use InteractsWithForms;

    protected string $view = 'filament.pages.booking-message-settings';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChatBubbleLeftEllipsis;

    protected static string|\UnitEnum|null $navigationGroup = 'اقامتی و هتلداری';

    protected static ?string $navigationLabel = 'پیام‌های رزرو';

    protected static ?string $title = 'پیامک‌های خودکار رزرو';

    /**
     * @var array<string, mixed>
     */
    public array $data = [];

    public static function accessControlPermission(): string
    {
        return 'booking-message-settings.access';
    }

    public function mount(): void
    {
        $setting = BookingMessageSetting::current();

        $this->form->fill([
            'confirmation_enabled' => $setting->confirmation_enabled,
            'reminder_enabled' => $setting->reminder_enabled,
            'reminder_days_before' => $setting->reminder_days_before,
            'feedback_enabled' => $setting->feedback_enabled,
            'confirmation_template' => $setting->confirmationText(),
            'reminder_template' => $setting->reminderText(),
            'feedback_template' => $setting->feedbackText(),
            'balance_reminder_enabled' => $setting->balance_reminder_enabled,
            'balance_reminder_days_before' => $setting->balance_reminder_days_before,
            'balance_reminder_template' => $setting->balanceText(),
        ]);
    }

    public function form(Schema $schema): Schema
    {
        $placeholders = 'متغیرها: '.implode('  ', BookingMessageSetting::PLACEHOLDERS).' ({link} لینک نظرسنجی و {precheckin_link} لینک پیش‌ثبت‌نام آنلاین مهمان است).';

        return $schema
            ->statePath('data')
            ->components([
                Toggle::make('confirmation_enabled')
                    ->label('وقتی رزرو «قطعی» شد، پیامک تأیید بفرست')
                    ->helperText('برای هر رزرو فقط یک بار. هزینه‌ی پیامک دارد و سرویس پیامک باید فعال باشد.'),
                Textarea::make('confirmation_template')->label('متن پیامک تأیید')->rows(3)->maxLength(500)->helperText($placeholders),
                Toggle::make('reminder_enabled')
                    ->label('پیش از ورود، پیامک یادآوری بفرست')
                    ->helperText('هر صبح برای رزروهای قطعی که به‌زودی می‌رسند.'),
                TextInput::make('reminder_days_before')
                    ->label('چند روز قبل از ورود؟')
                    ->numeric()->minValue(0)->maxValue(14)->required(),
                Textarea::make('reminder_template')->label('متن پیامک یادآوری')->rows(3)->maxLength(500)->helperText($placeholders),
                Toggle::make('balance_reminder_enabled')
                    ->label('پیش از ورود، پیامک مانده‌ی حساب بفرست')
                    ->helperText('فقط برای رزروهای قطعی که هنوز بدهکارند؛ هر رزرو یک بار. هر صبح ساعت ۹:۴۵.'),
                TextInput::make('balance_reminder_days_before')
                    ->label('چند روز قبل از ورود؟')
                    ->numeric()->minValue(0)->maxValue(30)->required(),
                Textarea::make('balance_reminder_template')->label('متن پیامک مانده‌ی حساب')->rows(3)->maxLength(500)->helperText($placeholders.' {balance} مانده‌ی حساب به تومان است.'),
                Toggle::make('feedback_enabled')
                    ->label('بعد از چک‌اوت، لینک نظرسنجی بفرست')
                    ->helperText('مهمان با یک امتیاز ۰ تا ۱۰ و یک نظر کوتاه جواب می‌دهد و پاسخ در «بازخورد مهمانان» می‌آید.'),
                Textarea::make('feedback_template')->label('متن پیامک نظرسنجی')->rows(3)->maxLength(500)->helperText($placeholders),
            ]);
    }

    public function save(): void
    {
        BookingMessageSetting::current()->update($this->form->getState());

        Notification::make()->title('تنظیمات پیام‌های رزرو ذخیره شد.')->success()->send();
    }
}
