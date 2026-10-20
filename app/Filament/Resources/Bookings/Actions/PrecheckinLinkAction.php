<?php

namespace App\Filament\Resources\Bookings\Actions;

use App\Domain\Crm\GuestMessenger;
use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\BookingPrecheckin;
use Filament\Actions\Action;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;

/**
 * «لینک پیش‌ثبت‌نام»: shows (and optionally texts) the link where the guest
 * fills in their national code, arrival time, companions and requests before
 * arriving. The link of a booking never changes.
 */
class PrecheckinLinkAction
{
    public static function make(): Action
    {
        return Action::make('precheckinLink')
            ->label('لینک پیش‌ثبت‌نام')
            ->icon(Heroicon::OutlinedClipboardDocumentCheck)
            ->color('gray')
            ->visible(fn (Booking $record) => in_array($record->status, [BookingStatus::Pending, BookingStatus::Confirmed], true))
            ->modalHeading('لینک پیش‌ثبت‌نام آنلاین مهمان')
            ->modalDescription(fn (Booking $record) => $record->precheckin?->isCompleted()
                ? 'مهمان این فرم را پر کرده است ('.$record->precheckin->completed_at->toJalali('Y/m/d H:i').'); تا پیش از ورود می‌تواند ویرایشش کند.'
                : 'مهمان هنوز فرم را پر نکرده است.')
            ->fillForm(fn (Booking $record) => ['send_sms' => filled($record->guest?->mobile)])
            ->schema([
                Toggle::make('send_sms')->label('لینک را پیامک هم بکن')->visible(fn (Booking $record) => filled($record->guest?->mobile)),
            ])
            ->action(function (Booking $record, array $data): void {
                $url = BookingPrecheckin::forBooking($record)->url();
                $body = $url;

                if (! empty($data['send_sms'])) {
                    $message = app(GuestMessenger::class)->sendSms(
                        $record->guest,
                        "{$record->guest->name} عزیز، برای سرعت گرفتن پذیرش، اطلاعات ورود رزرو {$record->reference_code} را اینجا تکمیل کنید: {$url}",
                        auth()->user(),
                    );

                    $body .= $message->error ? "\nپیامک ارسال نشد: {$message->error}" : "\nپیامک هم ارسال شد.";
                }

                Notification::make()->title('لینک پیش‌ثبت‌نام آماده است')->body($body)->success()->persistent()->send();
            });
    }
}
