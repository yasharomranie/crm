<?php

namespace App\Notifications;

use App\Filament\Pages\TodayArrivals;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Number;
use NotificationChannels\WebPush\WebPushChannel;
use NotificationChannels\WebPush\WebPushMessage;

/**
 * The front desk's morning summary (see App\Domain\Hospitality\MorningDigest).
 */
class HotelMorningDigestNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * @param  array{arrivals: int, departures: int, in_house: int, rooms_out: int, open_notes: int, yesterday_cash: int}  $summary
     */
    public function __construct(private readonly array $summary) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database', WebPushChannel::class];
    }

    public function line(): string
    {
        $parts = [
            Number::toPersian($this->summary['arrivals']).' ورود',
            Number::toPersian($this->summary['departures']).' خروج',
            Number::toPersian($this->summary['in_house']).' مقیم',
        ];

        if ($this->summary['rooms_out'] > 0) {
            $parts[] = Number::toPersian($this->summary['rooms_out']).' اتاق خارج از سرویس';
        }

        if ($this->summary['open_notes'] > 0) {
            $parts[] = Number::toPersian($this->summary['open_notes']).' یادداشت باز تحویل شیفت';
        }

        if ($this->summary['yesterday_cash'] !== 0) {
            $parts[] = 'دریافتی دیروز '.Number::toPersian(number_format($this->summary['yesterday_cash'])).' تومان';
        }

        return implode(' · ', $parts);
    }

    /**
     * @return array<string, mixed>
     */
    public function toDatabase(object $notifiable): array
    {
        return [
            'type' => 'hotel_morning_digest',
            'summary' => $this->summary,
            'message' => $this->line(),
        ];
    }

    public function toWebPush(object $notifiable, self $notification): WebPushMessage
    {
        return (new WebPushMessage)
            ->title('امروز در هتل')
            ->body($this->line())
            ->icon('/icons/icon-192.png')
            ->badge('/icons/icon-192.png')
            ->dir('rtl')
            ->lang('fa')
            ->data(['url' => TodayArrivals::getUrl()])
            ->options(['TTL' => 60 * 60 * 12]);
    }
}
