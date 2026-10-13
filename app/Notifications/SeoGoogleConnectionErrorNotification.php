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
 * Sent by App\Domain\Seo\SeoAlertService the moment a project's Search
 * Console connection moves from Connected to Error - see
 * SyncSearchConsoleDataJob, the only place that transition happens.
 * Before this, that failure was visible nowhere but a small red line on
 * the project's own edit page (last_sync_error) that nobody necessarily
 * ever opens, so every downstream مرکز سئو analysis could silently run
 * on stale data for weeks.
 */
class SeoGoogleConnectionErrorNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly SeoProject $project,
        private readonly string $error,
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
            ->title('قطع اتصال گوگل سرچ کنسول')
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
            ->subject('هشدار سئو: قطع اتصال گوگل سرچ کنسول')
            ->greeting('قطع اتصال گوگل سرچ کنسول')
            ->line($this->summary());
    }

    public function toSms(object $notifiable): string
    {
        return $this->summary();
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'type' => 'seo_google_connection_error',
            'seo_project_id' => $this->project->id,
            'message' => $this->summary(),
        ];
    }

    private function summary(): string
    {
        return "همگام‌سازی گوگل سرچ کنسول برای «{$this->project->name}» متوقف شد: {$this->error}";
    }
}
