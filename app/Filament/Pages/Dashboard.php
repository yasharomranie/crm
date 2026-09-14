<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\DealSourceChart;
use App\Filament\Widgets\DealWinLossTrendChart;
use App\Filament\Widgets\SalesFunnelChart;
use App\Filament\Widgets\SalesPipelineStatsOverview;
use App\Filament\Widgets\StaleDealsWidget;
use App\Filament\Widgets\TopSalesRepsWidget;
use Filament\Pages\Dashboard as BaseDashboard;

/**
 * Filament\Pages\Dashboard::getWidgets() renders literally every widget
 * registered on the panel (Filament::getWidgets()), which would include
 * the sales pipeline's own report row (SalesPipeline::getHeaderWidgets())
 * unless it's excluded here.
 *
 * An earlier attempt gated those widgets with a canView() that checked the
 * current request's route name - that broke as soon as a widget hydrated
 * over Livewire's own update endpoint instead of the page's route (lazy
 * loading, or clicking any action inside the widget, e.g. its Excel/PDF
 * export buttons), which made canView() flip to false mid-session and
 * Filament show a "couldn't load the page" error toast. Excluding the
 * widgets by class right here needs no request inspection at all, so it
 * can't suffer from that.
 */
class Dashboard extends BaseDashboard
{
    private const SALES_PIPELINE_ONLY_WIDGETS = [
        SalesPipelineStatsOverview::class,
        SalesFunnelChart::class,
        DealSourceChart::class,
        DealWinLossTrendChart::class,
        TopSalesRepsWidget::class,
        StaleDealsWidget::class,
    ];

    public function getWidgets(): array
    {
        return array_values(array_diff(parent::getWidgets(), self::SALES_PIPELINE_ONLY_WIDGETS));
    }
}
