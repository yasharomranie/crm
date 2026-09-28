<?php

namespace App\Filament\Pages;

use App\Filament\Pages\Concerns\HasAccessControlledPage;
use App\Filament\Widgets\SeoOverviewStatsWidget;
use App\Filament\Widgets\SeoUpcomingContentWidget;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;

/**
 * A single-glance answer to "is مرکز سئو working" for the CEO/SEO staff
 * audience the whole module was built for, instead of digging through the
 * separate Opportunities/Keyword Profiles/Suggestions/Content Calendar
 * resources one by one. Its own report row (like SalesPipeline's), kept
 * off the main App\Filament\Pages\Dashboard the same way.
 */
class SeoDashboard extends Page
{
    use HasAccessControlledPage;

    protected string $view = 'filament.pages.seo-dashboard';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChartBar;

    protected static string|\UnitEnum|null $navigationGroup = 'مرکز سئو';

    protected static ?string $navigationLabel = 'داشبورد سئو';

    protected static ?string $title = 'داشبورد سئو';

    public static function accessControlPermission(): string
    {
        return 'seo-dashboard.access';
    }

    /**
     * @return array<class-string>
     */
    protected function getHeaderWidgets(): array
    {
        return [
            SeoOverviewStatsWidget::class,
            SeoUpcomingContentWidget::class,
        ];
    }
}
