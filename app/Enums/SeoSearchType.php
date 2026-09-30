<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * The "type" request parameter Search Console's Search Analytics API takes
 * (never a "dimension") - which search vertical the numbers come from.
 * "web" is deliberately not a case here: it's the default for every
 * existing sync (SeoDailyMetric, SeoQueryMetric, ...), so it's never
 * stored in SeoSearchTypeMetric alongside these.
 */
enum SeoSearchType: string implements HasLabel
{
    case Image = 'image';
    case Video = 'video';
    case News = 'news';
    case Discover = 'discover';

    public function getLabel(): string
    {
        return match ($this) {
            self::Image => 'جستجوی تصویر',
            self::Video => 'جستجوی ویدیو',
            self::News => 'اخبار گوگل (Google News)',
            self::Discover => 'Google Discover',
        };
    }
}
