<?php

namespace App\Filament\Resources\Bookings\Actions;

use App\Enums\BookingPaymentKind;
use App\Enums\BookingPaymentMethod;
use App\Enums\BookingStatus;
use App\Models\Booking;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Number;
use InvalidArgumentException;

/**
 * «ثبت پرداخت»: writes one line of a booking's money ledger (a payment, an
 * advance or a refund). Shared by the bookings table and the booking's own
 * «پرداخت‌ها» panel; on a cancelled booking a payment is possible only while
 * the cancellation fee is unpaid, and otherwise only a refund.
 */
class RecordPaymentAction
{
    public static function make(): Action
    {
        return Action::make('recordPayment')
            ->label('ثبت پرداخت')
            ->icon(Heroicon::OutlinedBanknotes)
            ->color('success')
            ->visible(fn (Booking $record) => $record->status !== BookingStatus::Cancelled || $record->receivedAmount() > 0)
            ->modalHeading('ثبت پرداخت')
            ->modalDescription(fn (Booking $record) => ($record->status === BookingStatus::Cancelled ? 'جریمه‌ی لغو: ' : 'کل صورت‌حساب: ').self::money($record->amountOwed())
                .' — پرداخت‌شده: '.self::money($record->paidAmount())
                .' — مانده: '.self::money($record->balanceDue())
                .($record->refundableAmount() > 0 ? ' — قابل استرداد نقدی: '.self::money($record->refundableAmount()) : '')
                .'. پرداخت از کیف پول جداگانه و با دکمه‌ی «پرداخت از کیف پول» ثبت می‌شود.')
            ->fillForm(fn (Booking $record) => [
                'kind' => self::refundOnly($record) ? BookingPaymentKind::Refund->value : BookingPaymentKind::Payment->value,
                'method' => BookingPaymentMethod::Cash->value,
                'amount' => self::refundOnly($record) ? ($record->refundableAmount() ?: null) : $record->balanceDue(),
            ])
            ->schema(fn (Booking $record) => [
                Select::make('kind')
                    ->label('نوع')
                    ->options(self::refundOnly($record)
                        ? [BookingPaymentKind::Refund->value => BookingPaymentKind::Refund->getLabel()]
                        : collect(BookingPaymentKind::cases())->mapWithKeys(fn (BookingPaymentKind $kind) => [$kind->value => $kind->getLabel()])->all())
                    ->helperText('«پیش‌پرداخت» برای نگه‌داشتن رزرو است و مثل پرداخت از صورت‌حساب کم می‌شود؛ «استرداد» پول را به مهمان برمی‌گرداند.')
                    ->required(),
                Select::make('method')
                    ->label('روش')
                    ->options(collect(BookingPaymentMethod::cases())->mapWithKeys(fn (BookingPaymentMethod $method) => [$method->value => $method->getLabel()])->all())
                    ->required(),
                TextInput::make('amount')
                    ->label('مبلغ (تومان)')
                    ->numeric()
                    ->minValue(1)
                    ->required(),
                TextInput::make('reference')
                    ->label('شماره پیگیری / رسید')
                    ->maxLength(100),
                TextInput::make('note')
                    ->label('یادداشت')
                    ->maxLength(500),
            ])
            ->action(function (Booking $record, array $data) {
                try {
                    $record->recordPayment(
                        (int) $data['amount'],
                        BookingPaymentMethod::from($data['method']),
                        BookingPaymentKind::from($data['kind']),
                        $data['reference'] ?? null,
                        $data['note'] ?? null,
                        auth()->id(),
                    );
                } catch (InvalidArgumentException $exception) {
                    Notification::make()->title($exception->getMessage())->danger()->send();

                    return;
                }

                Notification::make()->title('پرداخت ثبت شد')->success()->send();
            });
    }

    /**
     * A cancelled booking with nothing left to pay can only take a refund.
     */
    private static function refundOnly(Booking $booking): bool
    {
        return $booking->status === BookingStatus::Cancelled && $booking->balanceDue() === 0;
    }

    private static function money(int $amount): string
    {
        return Number::toPersian(number_format($amount)).' تومان';
    }
}
