<?php

namespace App\Filament\Resources\Bookings\Actions;

use App\Domain\Crm\GuestMessenger;
use App\Domain\Payments\BookingPaymentLinkService;
use App\Domain\Payments\GatewayException;
use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\BookingPaymentLink;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Number;

/**
 * «لینک پرداخت آنلاین»: makes (or reuses) a link for the whole balance or part
 * of it, shows it to copy and can text it to the guest. The guest pays through
 * the gateway and the payment lands in the booking's ledger on its own.
 */
class PaymentLinkAction
{
    public static function make(): Action
    {
        return Action::make('paymentLink')
            ->label('لینک پرداخت')
            ->icon(Heroicon::OutlinedLink)
            ->color('success')
            ->visible(fn (Booking $record) => in_array($record->status, [BookingStatus::Pending, BookingStatus::Confirmed, BookingStatus::CheckedIn], true)
                && $record->balanceDue() > 0)
            ->modalHeading('لینک پرداخت آنلاین')
            ->modalDescription(fn (Booking $record) => 'مانده‌ی صورت‌حساب: '.Number::toPersian(number_format($record->balanceDue())).' تومان. مهمان بدون ورود به سامانه با این لینک پرداخت می‌کند؛ لینک '.Number::toPersian(BookingPaymentLink::VALID_DAYS).' روز اعتبار دارد.')
            ->fillForm(fn (Booking $record) => ['amount' => $record->balanceDue(), 'send_sms' => filled($record->guest?->mobile)])
            ->schema([
                TextInput::make('amount')->label('مبلغ (تومان)')->numeric()->minValue(1)->required(),
                Toggle::make('send_sms')->label('لینک را پیامک هم بکن')->visible(fn (Booking $record) => filled($record->guest?->mobile)),
            ])
            ->action(function (Booking $record, array $data): void {
                try {
                    $link = app(BookingPaymentLinkService::class)->linkFor($record, (int) $data['amount'], auth()->id());
                } catch (GatewayException $exception) {
                    Notification::make()->title('لینک ساخته نشد')->body($exception->getMessage())->danger()->persistent()->send();

                    return;
                }

                $body = $link->url();

                if (! empty($data['send_sms'])) {
                    $message = app(GuestMessenger::class)->sendSms($record->guest, sprintf(
                        '%s عزیز، برای پرداخت %s تومان از رزرو %s این لینک را باز کنید: %s',
                        $record->guest->name,
                        Number::toPersian(number_format($link->amount)),
                        $record->reference_code,
                        $link->url(),
                    ), auth()->user());

                    $body .= $message->error ? "\nپیامک ارسال نشد: {$message->error}" : "\nپیامک هم ارسال شد.";
                }

                Notification::make()->title('لینک پرداخت آماده است')->body($body)->success()->persistent()->send();
            });
    }
}
