<?php

namespace App\Notifications;

use App\Models\AdmissionTicket;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Number;
use NotificationChannels\WebPush\WebPushChannel;
use NotificationChannels\WebPush\WebPushMessage;

/**
 * Pushed when one ticket is rejected again and again at the gates in a
 * short time (see App\Domain\Ticketing\GateAnomalyDetector).
 */
class SuspiciousTicketNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly AdmissionTicket $ticket,
        private readonly int $rejections,
        private readonly int $gates,
    ) {}

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
            ->title('بلیت مشکوک در گیت')
            ->body(sprintf('بلیت %s در ۱۰ دقیقه‌ی اخیر %s بار در %s گیت رد شد.', $this->ticket->ticket_number, Number::toPersian($this->rejections), Number::toPersian($this->gates)))
            ->icon('/icons/icon-192.png')
            ->badge('/icons/icon-192.png')
            ->dir('rtl')
            ->lang('fa')
            ->data(['url' => route('filament.admin.resources.orders.view', $this->ticket->orderItem->order_id)])
            ->options(['TTL' => 60 * 60 * 6]);
    }
}
