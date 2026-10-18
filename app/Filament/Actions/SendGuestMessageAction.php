<?php

namespace App\Filament\Actions;

use App\Domain\Crm\GuestMessenger;
use App\Enums\MessageChannel;
use App\Enums\MessageDeliveryStatus;
use App\Models\Guest;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;

/**
 * «ارسال ایمیل» / «ارسال پیامک» on a guest's page. Only offered when the
 * guest actually has an address/number for that channel; the outcome
 * (including a failure and why) lands in their communication log.
 */
class SendGuestMessageAction
{
    public static function make(MessageChannel $channel): Action
    {
        $isEmail = $channel === MessageChannel::Email;

        return Action::make($isEmail ? 'sendEmail' : 'sendSms')
            ->label($isEmail ? 'ارسال ایمیل' : 'ارسال پیامک')
            ->icon($channel->getIcon())
            ->color('gray')
            ->authorize('update')
            ->visible(fn (Guest $record) => filled($isEmail ? $record->email : $record->mobile))
            ->modalHeading(fn (Guest $record) => ($isEmail ? 'ایمیل به ' : 'پیامک به ').$record->name)
            ->schema(array_filter([
                $isEmail ? TextInput::make('subject')->label('موضوع')->required()->maxLength(255) : null,
                Textarea::make('body')
                    ->label('متن پیام')
                    ->required()
                    ->rows(5)
                    ->maxLength($isEmail ? 5000 : 500),
                Toggle::make('is_marketing')
                    ->label('پیام تبلیغاتی / کمپین است')
                    ->helperText('پیام تبلیغاتی فقط به مشتریانی ارسال می‌شود که رضایت دریافتش را در همین کانال داده باشند؛ پیام خدماتی (پیگیری رزرو، پاسخ تیکت و...) نیازی به رضایت ندارد.'),
            ]))
            ->action(function (array $data, Guest $record) use ($isEmail): void {
                $messenger = app(GuestMessenger::class);

                $message = $isEmail
                    ? $messenger->sendEmail($record, $data['subject'], $data['body'], auth()->user(), (bool) ($data['is_marketing'] ?? false))
                    : $messenger->sendSms($record, $data['body'], auth()->user(), (bool) ($data['is_marketing'] ?? false));

                $notification = Notification::make();

                if ($message->status === MessageDeliveryStatus::Sent) {
                    $notification->title('پیام ارسال شد.')->success();
                } else {
                    $notification->title('پیام ارسال نشد.')->body($message->error)->danger()->persistent();
                }

                $notification->send();
            });
    }
}
