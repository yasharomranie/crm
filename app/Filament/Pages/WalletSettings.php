<?php

namespace App\Filament\Pages;

use App\Enums\LoyaltyTier;
use App\Filament\Pages\Concerns\HasAccessControlledPage;
use App\Models\WalletSetting;
use BackedEnum;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

/**
 * Singleton settings screen (backs the single WalletSetting row): the
 * cashback percentage each loyalty tier earns at checkout, and how long
 * earned cashback stays valid.
 */
class WalletSettings extends Page implements HasForms
{
    use HasAccessControlledPage;
    use InteractsWithForms;

    protected string $view = 'filament.pages.wallet-settings';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedWallet;

    protected static string|\UnitEnum|null $navigationGroup = 'CRM و مشتریان';

    protected static ?string $navigationLabel = 'تنظیمات کیف پول و کش‌بک';

    protected static ?string $title = 'تنظیمات کیف پول و کش‌بک';

    /**
     * @var array<string, mixed>
     */
    public array $data = [];

    public static function accessControlPermission(): string
    {
        return 'wallet-settings.access';
    }

    public function mount(): void
    {
        $this->form->fill(WalletSetting::current()->only([
            'cashback_bronze_percent', 'cashback_silver_percent', 'cashback_gold_percent', 'cashback_vip_percent',
            'cashback_expiry_days', 'expiry_reminder_days',
        ]));
    }

    public function form(Schema $schema): Schema
    {
        $percentField = fn (string $name, LoyaltyTier $tier) => TextInput::make($name)
            ->label('کش‌بک سطح '.$tier->getLabel().' (٪)')
            ->numeric()
            ->minValue(0)
            ->maxValue(100)
            ->step(0.01)
            ->required();

        return $schema
            ->statePath('data')
            ->components([
                Section::make('درصد کش‌بک هر سطح')
                    ->description('در چک‌اوت، درصدِ سطح وفاداری مشتری از مبلغی که با کیف پول پرداخت نشده به کیف پول او برمی‌گردد. ۰ یعنی بدون کش‌بک.')
                    ->columns(2)
                    ->schema([
                        $percentField('cashback_bronze_percent', LoyaltyTier::Bronze),
                        $percentField('cashback_silver_percent', LoyaltyTier::Silver),
                        $percentField('cashback_gold_percent', LoyaltyTier::Gold),
                        $percentField('cashback_vip_percent', LoyaltyTier::Vip),
                    ]),
                Section::make('انقضای کش‌بک')
                    ->columns(2)
                    ->schema([
                        TextInput::make('cashback_expiry_days')
                            ->label('اعتبار کش‌بک (روز)')
                            ->helperText('خالی = کش‌بک هرگز منقضی نمی‌شود. فقط کش‌بک‌های جدید اثر می‌گیرند؛ شارژ دستی و کش‌بک‌های قبلی هیچ‌وقت منقضی نمی‌شوند.')
                            ->numeric()
                            ->minValue(1)
                            ->maxValue(3650),
                        TextInput::make('expiry_reminder_days')
                            ->label('یادآوری پیامکی چند روز قبل از انقضا')
                            ->helperText('۰ = بدون یادآوری. پیامک فقط وقتی ارسال می‌شود که سرویس پیامک فعال باشد.')
                            ->numeric()
                            ->minValue(0)
                            ->maxValue(365)
                            ->required(),
                    ]),
            ]);
    }

    public function save(): void
    {
        WalletSetting::current()->update($this->form->getState());

        Notification::make()->title('تنظیمات کیف پول ذخیره شد.')->success()->send();
    }
}
