<?php

namespace App\Filament\Resources\Bookings\Actions;

use App\Enums\BookingPaymentMethod;
use App\Enums\BookingStatus;
use App\Models\Booking;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Number;
use InvalidArgumentException;

/**
 * «دریافت ودیعه» / «تسویه ودیعه»: the damage deposit a villa guest leaves is
 * kept apart from the folio. Receiving it records the money held; settling it
 * (usually at check-out) keeps the part that paid for damage and gives back the rest.
 */
class DamageDepositActions
{
    public static function receive(): Action
    {
        return Action::make('receiveDamageDeposit')
            ->label('دریافت ودیعه')
            ->icon(Heroicon::OutlinedShieldCheck)
            ->color('gray')
            ->visible(fn (Booking $record) => in_array($record->status, [BookingStatus::Pending, BookingStatus::Confirmed, BookingStatus::CheckedIn], true)
                && ! $record->damageDeposit?->isSettled())
            ->modalHeading('دریافت ودیعه‌ی خسارت')
            ->modalDescription(fn (Booking $record) => 'ودیعه جزو صورت‌حساب نیست و از مانده کم نمی‌شود؛ هنگام خروج یا برگردانده می‌شود یا بخشی از آن بابت خسارت کسر می‌شود.'
                .($record->damageDeposit?->held_amount ? ' تاکنون دریافت‌شده: '.self::money($record->damageDeposit->held_amount).'.' : ''))
            ->fillForm(['method' => BookingPaymentMethod::Cash->value])
            ->schema([
                TextInput::make('amount')->label('مبلغ ودیعه (تومان)')->numeric()->minValue(1)->required(),
                Select::make('method')
                    ->label('روش دریافت')
                    ->options(collect(BookingPaymentMethod::cases())->mapWithKeys(fn (BookingPaymentMethod $method) => [$method->value => $method->getLabel()])->all())
                    ->required(),
            ])
            ->action(function (Booking $record, array $data): void {
                try {
                    $record->receiveDamageDeposit((int) $data['amount'], BookingPaymentMethod::from($data['method']), auth()->id());
                } catch (InvalidArgumentException $exception) {
                    Notification::make()->title('ودیعه ثبت نشد')->body($exception->getMessage())->danger()->persistent()->send();

                    return;
                }

                Notification::make()->title('ودیعه ثبت شد')->success()->send();
            });
    }

    public static function settle(): Action
    {
        return Action::make('settleDamageDeposit')
            ->label('تسویه ودیعه')
            ->icon(Heroicon::OutlinedReceiptRefund)
            ->color('warning')
            ->visible(fn (Booking $record) => (bool) ($record->damageDeposit && $record->damageDeposit->outstandingAmount() > 0))
            ->modalHeading('تسویه‌ی ودیعه‌ی خسارت')
            ->modalDescription(fn (Booking $record) => 'ودیعه‌ی نزد ما: '.self::money($record->damageDeposit->outstandingAmount()).'. هر مبلغی که بابت خسارت کسر کنید نگه داشته می‌شود و بقیه باید به مهمان برگردد.')
            ->fillForm(['deduct' => 0])
            ->schema([
                TextInput::make('deduct')->label('مبلغ کسر بابت خسارت (تومان)')->numeric()->minValue(0)->default(0)->required()
                    ->helperText('صفر = کل ودیعه برگردانده می‌شود.'),
                Textarea::make('note')->label('توضیح خسارت')->maxLength(255),
            ])
            ->action(function (Booking $record, array $data): void {
                try {
                    $deposit = $record->settleDamageDeposit((int) $data['deduct'], $data['note'] ?? null, auth()->id());
                } catch (InvalidArgumentException $exception) {
                    Notification::make()->title('ودیعه تسویه نشد')->body($exception->getMessage())->danger()->persistent()->send();

                    return;
                }

                Notification::make()
                    ->title('ودیعه تسویه شد')
                    ->body($deposit->refunded_amount > 0 ? 'به مهمان برگردانید: '.self::money($deposit->refunded_amount) : 'کل ودیعه بابت خسارت نگه داشته شد.')
                    ->success()
                    ->persistent()
                    ->send();
            });
    }

    private static function money(int $amount): string
    {
        return Number::toPersian(number_format($amount)).' تومان';
    }
}
