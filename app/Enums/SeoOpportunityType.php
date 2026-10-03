<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum SeoOpportunityType: string implements HasLabel
{
    case CtrOpportunity = 'ctr_opportunity';
    case TrafficDrop = 'traffic_drop';
    case AnomalyPositive = 'anomaly_positive';
    case AnomalyNegative = 'anomaly_negative';
    case Cannibalization = 'cannibalization';
    case UrlCtrOpportunity = 'url_ctr_opportunity';
    case StalePage = 'stale_page';
    case InvisibleNewPage = 'invisible_new_page';

    public function getLabel(): string
    {
        return match ($this) {
            self::CtrOpportunity => 'فرصت بهبود CTR/رتبه',
            self::TrafficDrop => 'افت ترافیک',
            self::AnomalyPositive => 'رشد غیرعادی',
            self::AnomalyNegative => 'افت غیرعادی',
            self::Cannibalization => 'کانیبالیزیشن',
            self::UrlCtrOpportunity => 'فرصت بهبود CTR صفحه',
            self::StalePage => 'صفحه‌ی قدیمی بدون ترافیک',
            self::InvisibleNewPage => 'صفحه‌ی تازه بدون نمایش در گوگل',
        };
    }
}
