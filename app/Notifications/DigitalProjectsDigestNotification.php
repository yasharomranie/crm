<?php

namespace App\Notifications;

use App\Filament\Resources\DigitalProjects\DigitalProjectResource;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Number;
use NotificationChannels\WebPush\WebPushChannel;
use NotificationChannels\WebPush\WebPushMessage;

/**
 * One digest per recipient of the digital projects that need them: no
 * journal line for a week, a passed review date or an action past its
 * deadline (see App\Domain\DigitalProjects\DigitalProjectAlertService).
 */
class DigitalProjectsDigestNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * @param  list<string>  $unattended  names of projects without a recent journal line
     * @param  list<string>  $lateReviews  names of projects whose review date has passed
     * @param  list<string>  $lateActions  titles of actions past their deadline
     */
    public function __construct(
        private readonly array $unattended,
        private readonly array $lateReviews,
        private readonly array $lateActions,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database', WebPushChannel::class];
    }

    private function summary(): string
    {
        $parts = [];

        if ($this->unattended !== []) {
            $parts[] = Number::toPersian(count($this->unattended)).' پروژه بدون گزارش بیش از ۷ روز ('.$this->examples($this->unattended).')';
        }

        if ($this->lateReviews !== []) {
            $parts[] = Number::toPersian(count($this->lateReviews)).' پروژه با بررسی عقب‌افتاده ('.$this->examples($this->lateReviews).')';
        }

        if ($this->lateActions !== []) {
            $parts[] = Number::toPersian(count($this->lateActions)).' اقدام از مهلت گذشته ('.$this->examples($this->lateActions).')';
        }

        return implode(' — ', $parts);
    }

    /**
     * @param  list<string>  $names
     */
    private function examples(array $names): string
    {
        return implode('، ', array_slice($names, 0, 2)).(count($names) > 2 ? ' و ...' : '');
    }

    public function toWebPush(object $notifiable, self $notification): WebPushMessage
    {
        return (new WebPushMessage)
            ->title('پروژه‌های دیجیتال نیاز به رسیدگی دارند')
            ->body($this->summary())
            ->icon('/icons/icon-192.png')
            ->badge('/icons/icon-192.png')
            ->dir('rtl')
            ->lang('fa')
            ->data(['url' => DigitalProjectResource::getUrl('index')])
            ->options(['TTL' => 60 * 60 * 24]);
    }

    /**
     * @return array<string, mixed>
     */
    public function toDatabase(object $notifiable): array
    {
        return [
            'type' => 'digital_projects_digest',
            'message' => 'پروژه‌های دیجیتال: '.$this->summary(),
            'url' => DigitalProjectResource::getUrl('index'),
        ];
    }
}
