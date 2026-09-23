<?php

namespace App\Notifications;

use App\Models\EventReminder;
use App\Notifications\Channels\SmsChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use NotificationChannels\WebPush\WebPushChannel;
use NotificationChannels\WebPush\WebPushMessage;

/**
 * Delivered by events:send-reminders for one EventReminder, to one
 * recipient at a time. Which of the three channels below actually fire is
 * decided per-reminder (notify_push/notify_sms/notify_email - see
 * EventReminder), so the same reminder can go out as just a push, just an
 * SMS, all three, or any other combination the admin picked.
 */
class EventReminderNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(private readonly EventReminder $reminder) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        $channels = [];

        if ($this->reminder->notify_push) {
            $channels[] = WebPushChannel::class;
        }

        if ($this->reminder->notify_sms) {
            $channels[] = SmsChannel::class;
        }

        if ($this->reminder->notify_email) {
            $channels[] = 'mail';
        }

        return $channels;
    }

    public function toWebPush(object $notifiable, self $notification): WebPushMessage
    {
        return (new WebPushMessage)
            ->title('یادآوری رویداد')
            ->body($this->summary())
            ->icon('/icons/icon-192.png')
            ->badge('/icons/icon-192.png')
            ->dir('rtl')
            ->lang('fa')
            ->data(['url' => route('filament.admin.resources.events.index')])
            ->options(['TTL' => 60 * 60 * 24]);
    }

    public function toMail(object $notifiable): MailMessage
    {
        $event = $this->reminder->event;

        return (new MailMessage)
            ->subject('یادآوری رویداد: '.$event->title)
            ->greeting('یادآوری رویداد')
            ->line($this->summary())
            ->line($event->description ?: '');
    }

    public function toSms(object $notifiable): string
    {
        return $this->summary();
    }

    private function summary(): string
    {
        $event = $this->reminder->event;

        return "{$event->title} - ".$event->starts_at->format('Y-m-d H:i');
    }
}
