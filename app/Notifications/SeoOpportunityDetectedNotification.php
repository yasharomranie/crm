<?php

namespace App\Notifications;

use App\Models\SeoAlertSetting;
use App\Models\SeoOpportunity;
use App\Notifications\Channels\SmsChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use NotificationChannels\WebPush\WebPushChannel;
use NotificationChannels\WebPush\WebPushMessage;

/**
 * Sent by App\Domain\Seo\SeoAlertService whenever OpportunityEngine
 * detects a brand-new (not a refreshed/existing) High-priority
 * opportunity - a Low/Medium one, or one that already existed and is
 * just being refreshed with new numbers, does not page anyone.
 */
class SeoOpportunityDetectedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(private readonly SeoOpportunity $opportunity) {}

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
            ->title('فرصت سئوی مهم شناسایی شد')
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
            ->subject('هشدار سئو: فرصت مهم جدید')
            ->greeting('فرصت سئوی مهم شناسایی شد')
            ->line($this->summary());
    }

    public function toSms(object $notifiable): string
    {
        return $this->summary();
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'type' => 'seo_opportunity_detected',
            'seo_opportunity_id' => $this->opportunity->id,
            'message' => $this->summary(),
        ];
    }

    private function summary(): string
    {
        $project = $this->opportunity->project;

        return "{$this->opportunity->title} ({$project->name})";
    }
}
