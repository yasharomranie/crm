<?php

namespace App\Filament\Resources\Bookings\Actions;

use App\Domain\Crm\BookingMessenger;
use App\Enums\BookingStatus;
use App\Enums\MessageDeliveryStatus;
use App\Models\Booking;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;

/**
 * «ارسال پیامک رزرو»: texts the guest the confirmation or the arrival
 * reminder right now, with the wording from «پیام‌های رزرو». It always sends,
 * even if the same message went out before (staff pressed it on purpose).
 */
class SendBookingSmsAction
{
    public static function make(): Action
    {
        return Action::make('sendBookingSms')
            ->label('پیامک رزرو')
            ->icon(Heroicon::OutlinedChatBubbleLeftEllipsis)
            ->color('gray')
            ->visible(fn (Booking $record) => filled($record->guest?->mobile)
                && in_array($record->status, [BookingStatus::Pending, BookingStatus::Confirmed], true))
            ->modalHeading('ارسال پیامک به مهمان')
            ->modalDescription('متن پیامک از صفحه‌ی «پیام‌های رزرو» گرفته می‌شود و در مکالمات مهمان ثبت می‌شود.')
            ->schema([
                Select::make('type')
                    ->label('کدام پیامک؟')
                    ->options(['confirmation' => 'تأیید رزرو', 'reminder' => 'یادآوری ورود'])
                    ->default('confirmation')
                    ->required(),
            ])
            ->action(function (Booking $record, array $data): void {
                $messenger = app(BookingMessenger::class);

                $message = $data['type'] === 'reminder'
                    ? $messenger->sendArrivalReminder($record, true)
                    : $messenger->sendConfirmation($record, true);

                $notification = Notification::make();

                if ($message?->status === MessageDeliveryStatus::Sent) {
                    $notification->title('پیامک ارسال شد.')->success();
                } else {
                    $notification->title('پیامک ارسال نشد.')->body($message?->error)->danger()->persistent();
                }

                $notification->send();
            });
    }
}
