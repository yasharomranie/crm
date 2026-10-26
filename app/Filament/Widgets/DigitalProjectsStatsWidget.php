<?php

namespace App\Filament\Widgets;

use App\Enums\DigitalProjectStatus;
use App\Models\DigitalProject;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Number;

/**
 * The few numbers that matter above the digital-projects table: how many
 * projects there are, which ones need attention and whose review is late.
 */
class DigitalProjectsStatsWidget extends StatsOverviewWidget
{
    protected function getStats(): array
    {
        return [
            Stat::make('کل پروژه‌ها', Number::toPersian(DigitalProject::query()->count()))
                ->descriptionIcon(Heroicon::OutlinedGlobeAlt)
                ->description('سایت، شبکه‌های اجتماعی و پیام‌رسان‌ها')
                ->color('primary'),
            Stat::make('فعال', Number::toPersian(DigitalProject::query()->where('status', DigitalProjectStatus::Active->value)->count()))
                ->descriptionIcon(Heroicon::OutlinedCheckCircle)
                ->description('در حال کار و بهره‌برداری')
                ->color('success'),
            Stat::make('نیازمند توجه', Number::toPersian(DigitalProject::query()->where('status', DigitalProjectStatus::NeedsAttention->value)->count()))
                ->descriptionIcon(Heroicon::OutlinedExclamationTriangle)
                ->description('به رسیدگی شما نیاز دارند')
                ->color('warning'),
            Stat::make('بررسی عقب‌افتاده', Number::toPersian(DigitalProject::query()->reviewOverdue()->count()))
                ->descriptionIcon(Heroicon::OutlinedClock)
                ->description('موعد بررسی‌شان گذشته')
                ->color('danger'),
        ];
    }
}
