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
    case ImpressionAnomalyPositive = 'impression_anomaly_positive';
    case ImpressionAnomalyNegative = 'impression_anomaly_negative';
    case PositionAnomalyPositive = 'position_anomaly_positive';
    case PositionAnomalyNegative = 'position_anomaly_negative';
    case CtrAnomalyPositive = 'ctr_anomaly_positive';
    case CtrAnomalyNegative = 'ctr_anomaly_negative';
    case PageAnomalyPositive = 'page_anomaly_positive';
    case PageAnomalyNegative = 'page_anomaly_negative';
    case QueryAnomalyPositive = 'query_anomaly_positive';
    case QueryAnomalyNegative = 'query_anomaly_negative';

    public function getLabel(): string
    {
        return match ($this) {
            self::CtrOpportunity => 'فرصت بهبود CTR/رتبه',
            self::TrafficDrop => 'افت ترافیک',
            self::AnomalyPositive => 'رشد غیرعادی کلیک (کل سایت)',
            self::AnomalyNegative => 'افت غیرعادی کلیک (کل سایت)',
            self::Cannibalization => 'کانیبالیزیشن',
            self::UrlCtrOpportunity => 'فرصت بهبود CTR صفحه',
            self::StalePage => 'صفحه‌ی قدیمی بدون ترافیک',
            self::InvisibleNewPage => 'صفحه‌ی تازه بدون نمایش در گوگل',
            self::ImpressionAnomalyPositive => 'رشد غیرعادی ایمپرشن (کل سایت)',
            self::ImpressionAnomalyNegative => 'افت غیرعادی ایمپرشن (کل سایت)',
            self::PositionAnomalyPositive => 'بهبود غیرعادی میانگین رتبه (کل سایت)',
            self::PositionAnomalyNegative => 'افت غیرعادی میانگین رتبه (کل سایت)',
            self::CtrAnomalyPositive => 'رشد غیرعادی نرخ کلیک - CTR (کل سایت)',
            self::CtrAnomalyNegative => 'افت غیرعادی نرخ کلیک - CTR (کل سایت)',
            self::PageAnomalyPositive => 'رشد غیرعادی کلیک یک صفحه',
            self::PageAnomalyNegative => 'افت غیرعادی کلیک یک صفحه',
            self::QueryAnomalyPositive => 'رشد غیرعادی کلیک یک کلمه‌کلیدی',
            self::QueryAnomalyNegative => 'افت غیرعادی کلیک یک کلمه‌کلیدی',
        };
    }
}
