<?php

namespace App\Filament\Resources\BookingGroups\Actions;

use App\Enums\BookingPaymentMethod;
use App\Models\BookingGroup;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Number;
use InvalidArgumentException;

/**
 * The group-wide buttons: one payment shared over the rooms, the printable
 * invoice of the whole group, and cancelling every room that has not arrived.
 */
class BookingGroupActions
{
    public static function pay(): Action
    {
        return Action::make('groupPayment')
            ->label('ثبت پرداخت گروهی')
            ->icon(Heroicon::OutlinedBanknotes)
            ->color('success')
            ->visible(fn (BookingGroup $record) => $record->balanceDue() > 0)
            ->modalHeading('پرداخت گروهی')
            ->modalDescription(fn (BookingGroup $record) => 'مانده‌ی کل گروه: '.self::money($record->balanceDue()).'. مبلغ به ترتیب تاریخ ورود بین اتاق‌ها پخش می‌شود و برای هر اتاق از مانده‌ی خودش بیشتر نمی‌شود.')
            ->fillForm(fn (BookingGroup $record) => ['amount' => $record->balanceDue(), 'method' => BookingPaymentMethod::Cash->value])
            ->schema([
                TextInput::make('amount')->label('مبلغ (تومان)')->numeric()->minValue(1)->required(),
                Select::make('method')
                    ->label('روش')
                    ->options(collect(BookingPaymentMethod::cases())->mapWithKeys(fn (BookingPaymentMethod $method) => [$method->value => $method->getLabel()])->all())
                    ->required(),
                TextInput::make('reference')->label('شماره پیگیری / رسید')->maxLength(100),
                TextInput::make('note')->label('یادداشت')->maxLength(300),
            ])
            ->action(function (BookingGroup $record, array $data): void {
                try {
                    $allocation = $record->receivePayment((int) $data['amount'], BookingPaymentMethod::from($data['method']), $data['reference'] ?? null, $data['note'] ?? null, auth()->id());
                } catch (InvalidArgumentException $exception) {
                    Notification::make()->title('پرداخت ثبت نشد')->body($exception->getMessage())->danger()->persistent()->send();

                    return;
                }

                Notification::make()->title('پرداخت گروهی ثبت شد')->body('بین '.Number::toPersian(count($allocation)).' اتاق پخش شد.')->success()->send();
            });
    }

    public static function invoice(): Action
    {
        return Action::make('groupInvoice')
            ->label('چاپ صورت‌حساب گروه')
            ->icon(Heroicon::OutlinedPrinter)
            ->color('gray')
            ->url(fn (BookingGroup $record) => route('booking-groups.invoice', $record))
            ->openUrlInNewTab();
    }

    public static function cancelAll(): Action
    {
        return Action::make('cancelGroup')
            ->label('لغو همهٔ اتاق‌ها')
            ->icon(Heroicon::OutlinedXCircle)
            ->color('danger')
            ->visible(fn (BookingGroup $record) => $record->bookings()->whereIn('status', ['pending', 'confirmed'])->exists())
            ->modalHeading('لغو همهٔ اتاق‌های گروه')
            ->modalDescription('هر اتاقِ «در انتظار» یا «قطعی» با شرایط لغو خودش لغو می‌شود. اتاقی که مهمانش وارد شده یا خارج شده دست نمی‌خورد؛ برای لغو تک‌اتاق از فهرست رزروها استفاده کنید.')
            ->schema([
                Textarea::make('reason')->label('دلیل (اختیاری)')->maxLength(255),
                Toggle::make('waive_fee')->label('جریمه‌ی لغو بخشیده شود'),
            ])
            ->action(function (BookingGroup $record, array $data): void {
                $count = $record->cancelAll($data['reason'] ?? null, (bool) ($data['waive_fee'] ?? false));

                Notification::make()->title(Number::toPersian($count).' اتاق لغو شد')->success()->send();
            });
    }

    private static function money(int $amount): string
    {
        return Number::toPersian(number_format($amount)).' تومان';
    }
}
