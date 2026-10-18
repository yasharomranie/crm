<?php

namespace App\Filament\Pages;

use App\Filament\Pages\Concerns\HasAccessControlledPage;
use App\Models\PaymentGatewaySetting;
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
 * Singleton settings screen (backs the single PaymentGatewaySetting row)
 * for the Zarinpal merchant that customers' online wallet top-ups go to.
 */
class PaymentGatewaySettings extends Page implements HasForms
{
    use HasAccessControlledPage;
    use InteractsWithForms;

    protected string $view = 'filament.pages.payment-gateway-settings';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCreditCard;

    protected static string|\UnitEnum|null $navigationGroup = 'زیرساخت سازمانی';

    protected static ?string $navigationLabel = 'درگاه پرداخت آنلاین';

    protected static ?string $title = 'درگاه پرداخت آنلاین (زرین‌پال)';

    /**
     * @var array<string, mixed>
     */
    public array $data = [];

    public static function accessControlPermission(): string
    {
        return 'payment-gateway-settings.access';
    }

    public function mount(): void
    {
        $this->form->fill(PaymentGatewaySetting::current()->only([
            'is_enabled', 'merchant_id', 'is_sandbox', 'min_topup_amount', 'max_topup_amount',
        ]));
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                Toggle::make('is_enabled')
                    ->label('فعال بودن شارژ آنلاین کیف پول')
                    ->helperText('تا وقتی فعال نباشد و مرچنت‌کد وارد نشود، دکمه‌ی شارژ در وب‌اپ مشتری دیده نمی‌شود.'),
                TextInput::make('merchant_id')
                    ->label('مرچنت‌کد زرین‌پال')
                    ->password()
                    ->revealable()
                    ->maxLength(100),
                Toggle::make('is_sandbox')
                    ->label('حالت آزمایشی (سندباکس)')
                    ->helperText('فقط برای تست؛ در حالت آزمایشی پول واقعی جابه‌جا نمی‌شود.'),
                TextInput::make('min_topup_amount')
                    ->label('حداقل مبلغ شارژ (تومان)')
                    ->numeric()
                    ->minValue(1000)
                    ->required(),
                TextInput::make('max_topup_amount')
                    ->label('حداکثر مبلغ شارژ (تومان)')
                    ->numeric()
                    ->minValue(1000)
                    ->required(),
            ]);
    }

    public function save(): void
    {
        PaymentGatewaySetting::current()->update($this->form->getState());

        Notification::make()->title('تنظیمات درگاه پرداخت ذخیره شد.')->success()->send();
    }
}
