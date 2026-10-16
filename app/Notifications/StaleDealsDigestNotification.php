<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Number;
use NotificationChannels\WebPush\WebPushChannel;
use NotificationChannels\WebPush\WebPushMessage;

/**
 * One push per recipient summarizing the open deals that have gone quiet
 * (see App\Domain\Crm\StaleDealAlertService) - a digest, not one push per
 * deal, so a backlog of old deals can't flood someone's phone.
 */
class StaleDealsDigestNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * @param  list<string>  $dealTitles
     */
    public function __construct(private readonly int $count, private readonly array $dealTitles) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return [WebPushChannel::class];
    }

    public function toWebPush(object $notifiable, self $notification): WebPushMessage
    {
        $examples = implode('، ', array_slice($this->dealTitles, 0, 3));
        $more = $this->count > 3 ? ' و ...' : '';

        return (new WebPushMessage)
            ->title(Number::toPersian($this->count).' معامله‌ی راکد نیاز به پیگیری دارد')
            ->body($examples.$more)
            ->icon('/icons/icon-192.png')
            ->badge('/icons/icon-192.png')
            ->dir('rtl')
            ->lang('fa')
            ->data(['url' => route('filament.admin.pages.sales-pipeline')])
            ->options(['TTL' => 60 * 60 * 24]);
    }
}
