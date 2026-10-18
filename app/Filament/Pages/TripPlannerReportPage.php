<?php

namespace App\Filament\Pages;

use App\Domain\TripPlanner\TripPlannerReport;
use App\Filament\Pages\Concerns\HasAccessControlledPage;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;

class TripPlannerReportPage extends Page
{
    use HasAccessControlledPage;

    protected string $view = 'filament.pages.trip-planner-report';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChartBar;

    protected static string|\UnitEnum|null $navigationGroup = 'تریپ‌پلنر';

    protected static ?string $navigationLabel = 'گزارش تریپ‌پلنر';

    protected static ?string $title = 'گزارش تریپ‌پلنر';

    public int $periodDays = 30;

    public static function accessControlPermission(): string
    {
        return 'trip-planner-report.access';
    }

    /**
     * @return array<string, mixed>
     */
    public function report(): array
    {
        return app(TripPlannerReport::class)->build($this->periodDays);
    }
}
