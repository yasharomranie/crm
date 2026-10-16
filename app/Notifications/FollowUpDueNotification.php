<?php

namespace App\Notifications;

use App\Models\Task;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use NotificationChannels\WebPush\WebPushChannel;
use NotificationChannels\WebPush\WebPushMessage;

/**
 * Pushed to a CRM follow-up's assignee once its due time arrives (see
 * App\Domain\Crm\FollowUpReminderService).
 */
class FollowUpDueNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(private readonly Task $task) {}

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
            ->title('زمان پیگیری رسید')
            ->body($this->task->title)
            ->icon('/icons/icon-192.png')
            ->badge('/icons/icon-192.png')
            ->dir('rtl')
            ->lang('fa')
            ->data(['url' => route('filament.admin.pages.dashboard')])
            ->options(['TTL' => 60 * 60 * 24]);
    }
}
