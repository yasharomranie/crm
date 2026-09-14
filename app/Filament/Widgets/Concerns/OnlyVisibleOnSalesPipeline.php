<?php

namespace App\Filament\Widgets\Concerns;

/**
 * Every widget under app/Filament/Widgets is auto-discovered onto the main
 * Filament\Pages\Dashboard (it renders Filament::getWidgets() - literally
 * every registered widget). The sales-pipeline report widgets belong only
 * on SalesPipeline::getHeaderWidgets(), so this keeps them off the main
 * dashboard by only reporting canView() true while that page's route is
 * the one actually being rendered.
 */
trait OnlyVisibleOnSalesPipeline
{
    public static function canView(): bool
    {
        return (bool) request()?->routeIs('filament.admin.pages.sales-pipeline');
    }
}
