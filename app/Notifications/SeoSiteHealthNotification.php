<?php

namespace App\Notifications;

use App\Models\SeoAlertSetting;
use App\Models\SeoProject;
use App\Notifications\Channels\SmsChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use NotificationChannels\WebPush\WebPushChannel;
use NotificationChannels\WebPush\WebPushMessage;

/**
 * Sent by App\Domain\Seo\SeoAlertService when a project's website stops
 * answering, answers again, or has an SSL certificate close to its end -
 * see App\Domain\Seo\SiteHealth\SiteHealthMonitor.
 */
class SeoSiteHealthNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly SeoProject $project,
        private readonly string $message,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        $settings = SeoAlertSetting::current();
        $channels = ['database'];

        if ($settings->notify_push) {
            $channels[] = WebPushChannel::class;
        }

        if ($settings->notify_sms) {
            $channels[] = SmsChannel::class;
        }

        if ($settings->notify_email) {
            $channels[] = 'mail';
        }

        return $channels;
    }

    public function toWebPush(object $notifiable, self $notification): WebPushMessage
    {
        return (new WebPushMessage)
            ->title('وضعیت سایت')
            ->body($this->summary())
            ->icon('/icons/icon-192.png')
            ->badge('/icons/icon-192.png')
            ->dir('rtl')
            ->lang('fa')
            ->options(['TTL' => 60 * 60 * 24]);
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('هشدار سئو: وضعیت سایت')
            ->greeting('وضعیت سایت')
            ->line($this->summary());
    }

    public function toSms(object $notifiable): string
    {
        return $this->summary();
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'type' => 'seo_site_health',
            'seo_project_id' => $this->project->id,
            'message' => $this->summary(),
        ];
    }

    private function summary(): string
    {
        return $this->message;
    }
}
