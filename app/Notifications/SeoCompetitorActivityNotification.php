<?php

namespace App\Notifications;

use App\Models\SeoAlertSetting;
use App\Models\SeoProjectCompetitor;
use App\Notifications\Channels\SmsChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use NotificationChannels\WebPush\WebPushChannel;
use NotificationChannels\WebPush\WebPushMessage;

/**
 * Sent by App\Domain\Seo\SeoAlertService whenever App\Domain\Seo\
 * CompetitorSitemapCrawler finds that a competitor's sitemap grew since
 * the last sync - a competitor investing in new content is a market
 * signal worth knowing about as it happens, not only once a week when
 * someone remembers to open "تحلیل شکاف رقبا" manually.
 */
class SeoCompetitorActivityNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly SeoProjectCompetitor $competitor,
        private readonly int $newPageCount,
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
            ->title('فعالیت جدید رقیب')
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
            ->subject('هشدار سئو: فعالیت جدید رقیب')
            ->greeting('یکی از رقبای شما محتوای جدید منتشر کرد')
            ->line($this->summary());
    }

    public function toSms(object $notifiable): string
    {
        return $this->summary();
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'type' => 'seo_competitor_activity',
            'seo_project_competitor_id' => $this->competitor->id,
            'message' => $this->summary(),
        ];
    }

    private function summary(): string
    {
        $project = $this->competitor->project;

        return "رقیب «{$this->competitor->domain}» {$this->newPageCount} صفحه جدید منتشر کرد ({$project->name}).";
    }
}
