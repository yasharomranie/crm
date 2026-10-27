<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/** The numbers someone reads off a channel by hand (the platforms give no feed we can use). */
enum DigitalProjectMetric: string implements HasLabel
{
    case Followers = 'followers';
    case Views = 'views';
    case Visitors = 'visitors';
    case Posts = 'posts';
    case Stories = 'stories';
    case Purchases = 'purchases';

    public function getLabel(): string
    {
        return match ($this) {
            self::Followers => 'دنبال‌کننده / عضو',
            self::Views => 'بازدید',
            self::Visitors => 'بازدیدکنندهٔ سایت',
            self::Posts => 'تعداد پست',
            self::Stories => 'تعداد استوری',
            self::Purchases => 'خرید از سایت',
        };
    }
}
