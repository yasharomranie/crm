<?php

namespace App\Notifications;

use App\Filament\Pages\AiAssistant;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Number;
use NotificationChannels\WebPush\WebPushChannel;
use NotificationChannels\WebPush\WebPushMessage;

/**
 * The morning digest of the management assistant's critical alerts (see
 * App\Domain\Ai\AssistantAlertDigestService).
 */
class AssistantAlertsDigestNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * @param  list<string>  $messages
     */
    public function __construct(private readonly array $messages) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return [WebPushChannel::class];
    }

    public function toWebPush(object $notifiable, self $notification): WebPushMessage
    {
        return (new WebPushMessage)
            ->title(sprintf('%s هشدار مهم در سیستم', Number::toPersian(count($this->messages))))
            ->body(implode("\n", array_slice($this->messages, 0, 3)))
            ->icon('/icons/icon-192.png')
            ->badge('/icons/icon-192.png')
            ->dir('rtl')
            ->lang('fa')
            ->data(['url' => AiAssistant::getUrl()])
            ->options(['TTL' => 60 * 60 * 12]);
    }
}
