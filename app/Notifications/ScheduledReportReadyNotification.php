<?php

namespace App\Notifications;

use App\Filament\Pages\AiAssistant;
use App\Models\AiScheduledReport;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;
use NotificationChannels\WebPush\WebPushChannel;
use NotificationChannels\WebPush\WebPushMessage;

/**
 * Tells a manager their scheduled assistant report is ready to read.
 */
class ScheduledReportReadyNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(private readonly AiScheduledReport $report) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database', WebPushChannel::class];
    }

    public function toWebPush(object $notifiable, self $notification): WebPushMessage
    {
        return (new WebPushMessage)
            ->title('گزارش دستیار هوشمند آماده است')
            ->body(Str::limit($this->report->prompt, 100))
            ->icon('/icons/icon-192.png')
            ->badge('/icons/icon-192.png')
            ->dir('rtl')
            ->lang('fa')
            ->data(['url' => AiAssistant::getUrl()])
            ->options(['TTL' => 60 * 60 * 12]);
    }

    /**
     * @return array<string, mixed>
     */
    public function toDatabase(object $notifiable): array
    {
        return [
            'type' => 'assistant_scheduled_report',
            'ai_scheduled_report_id' => $this->report->id,
            'message' => 'گزارش دستیار هوشمند آماده است: '.Str::limit($this->report->prompt, 100),
        ];
    }
}
