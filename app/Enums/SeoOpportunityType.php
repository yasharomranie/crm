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

    public function getLabel(): string
    {
        return match ($this) {
            self::CtrOpportunity => 'فرصت بهبود CTR/رتبه',
            self::TrafficDrop => 'افت ترافیک',
            self::AnomalyPositive => 'رشد غیرعادی',
            self::AnomalyNegative => 'افت غیرعادی',
            self::Cannibalization => 'کانیبالیزیشن',
        };
    }
}
