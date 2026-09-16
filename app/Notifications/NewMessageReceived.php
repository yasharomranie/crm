<?php

namespace App\Notifications;

use App\Models\Message;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;
use NotificationChannels\WebPush\WebPushChannel;
use NotificationChannels\WebPush\WebPushMessage;

/**
 * Sent to every other participant of a conversation the moment a message
 * lands in it - the concrete first use of this app's push-notification
 * infrastructure, so someone finds out about a new message even with the
 * panel closed. Delivered as a real Web Push, not stored/read like the
 * database channel - there's no in-app notification bell here, just the
 * existing unread-count badge (Message::totalUnreadFor()) this doesn't
 * replace or duplicate.
 */
class NewMessageReceived extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(private readonly Message $message) {}

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
            ->title($this->message->sender->name)
            ->body(Str::limit($this->message->body, 90))
            ->icon('/icons/icon-192.png')
            ->badge('/icons/icon-192.png')
            ->dir('rtl')
            ->lang('fa')
            ->data(['url' => route('filament.admin.pages.messenger')])
            ->options(['TTL' => 60 * 60 * 24]);
    }
}
