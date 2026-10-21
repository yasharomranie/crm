<?php

namespace App\Notifications;

use App\Filament\Pages\AiMissionRoom;
use App\Models\AiMission;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use NotificationChannels\WebPush\WebPushChannel;
use NotificationChannels\WebPush\WebPushMessage;

/**
 * Tells a manager something about one of their missions needs a look: a draft
 * decision is ready, a run finished, failed, or is close to its token budget.
 */
class MissionAttentionNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * @param  string  $kind  ready | done | failed | budget_warning
     */
    public function __construct(private readonly AiMission $mission, private readonly string $kind, private readonly string $message) {}

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
            ->title('اتاق عملیات: '.$this->mission->title)
            ->body($this->message)
            ->icon('/icons/icon-192.png')
            ->badge('/icons/icon-192.png')
            ->dir('rtl')
            ->lang('fa')
            ->data(['url' => AiMissionRoom::getUrl(['mission' => $this->mission->id])])
            ->options(['TTL' => 60 * 60 * 12]);
    }

    /**
     * @return array<string, mixed>
     */
    public function toDatabase(object $notifiable): array
    {
        return [
            'type' => 'ai_mission_'.$this->kind,
            'ai_mission_id' => $this->mission->id,
            'message' => 'اتاق عملیات «'.$this->mission->title.'»: '.$this->message,
        ];
    }
}
