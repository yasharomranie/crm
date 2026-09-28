<?php

namespace App\Notifications;

use App\Models\SeoAlertSetting;
use App\Models\SeoTrackedKeyword;
use App\Notifications\Channels\SmsChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use NotificationChannels\WebPush\WebPushChannel;
use NotificationChannels\WebPush\WebPushMessage;

/**
 * Sent by App\Domain\Seo\SeoAlertService when a tracked keyword's Google
 * rank falls out of the top ۱۰ or drops by at least the configured
 * threshold ("تنظیمات هشدار سئو") - which channels actually fire is a
 * single app-wide choice (SeoAlertSetting), unlike EventReminderNotification
 * where each reminder picks its own channels.
 */
class SeoRankDropNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly SeoTrackedKeyword $keyword,
        private readonly ?int $previousPosition,
        private readonly ?int $newPosition,
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
            ->title('افت رتبه در ترکر کلمه کلیدی')
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
            ->subject('هشدار سئو: افت رتبه')
            ->greeting('افت رتبه در ترکر کلمه کلیدی')
            ->line($this->summary());
    }

    public function toSms(object $notifiable): string
    {
        return $this->summary();
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'type' => 'seo_rank_drop',
            'seo_tracked_keyword_id' => $this->keyword->id,
            'message' => $this->summary(),
        ];
    }

    private function summary(): string
    {
        $project = $this->keyword->project;
        $from = $this->previousPosition ?? 'خارج از ۱۰ نتیجه اول';
        $to = $this->newPosition ?? 'خارج از ۱۰ نتیجه اول';

        return "«{$this->keyword->keyword}» ({$project->name}) از رتبه {$from} به {$to} افت کرد.";
    }
}
