<?php

namespace App\Notifications;

use App\Models\GuestTicket;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use NotificationChannels\WebPush\WebPushChannel;
use NotificationChannels\WebPush\WebPushMessage;

/**
 * Pushed once when a support ticket passes its SLA deadline while still
 * open (see App\Domain\Crm\TicketSlaBreachService).
 */
class TicketSlaBreachedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(private readonly GuestTicket $ticket) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return [WebPushChannel::class];
    }

    public function toWebPush(object $notifiable, self $notification): WebPushMessage
    {
        $customer = $this->ticket->guest?->name;

        return (new WebPushMessage)
            ->title('مهلت پاسخ یک تیکت گذشت')
            ->body($customer ? "{$this->ticket->subject} - {$customer}" : $this->ticket->subject)
            ->icon('/icons/icon-192.png')
            ->badge('/icons/icon-192.png')
            ->dir('rtl')
            ->lang('fa')
            ->data(['url' => route('filament.admin.resources.guest-tickets.edit', $this->ticket)])
            ->options(['TTL' => 60 * 60 * 24]);
    }
}
