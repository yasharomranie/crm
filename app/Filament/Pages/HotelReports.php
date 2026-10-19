<?php

namespace App\Filament\Pages;

use App\Domain\Hospitality\HotelReport;
use App\Filament\Pages\Concerns\HasAccessControlledPage;
use BackedEnum;
use Carbon\CarbonInterface;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Number;

/**
 * «گزارش‌های هتلداری»: occupancy, ADR, RevPAR, the split by channel and
 * source, cancellations and a 30-day forecast, for a period picked on the page.
 */
class HotelReports extends Page
{
    use HasAccessControlledPage;

    protected string $view = 'filament.pages.hotel-reports';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChartBar;

    protected static string|\UnitEnum|null $navigationGroup = 'اقامتی و هتلداری';

    protected static ?string $navigationLabel = 'گزارش‌های هتلداری';

    protected static ?string $title = 'گزارش‌های هتلداری';

    public string $period = 'this_month';

    /** @var array<string, string> */
    public const PERIODS = [
        'this_month' => 'این ماه',
        'last_month' => 'ماه گذشته',
        'last_30' => '۳۰ روز اخیر',
        'next_30' => '۳۰ روز آینده',
        'this_year' => 'از ابتدای سال میلادی',
    ];

    public static function accessControlPermission(): string
    {
        return 'hotel-reports.access';
    }

    /**
     * @return array<string, mixed>
     */
    public function report(): array
    {
        [$from, $to] = $this->range();

        return app(HotelReport::class)->build($from, $to);
    }

    /**
     * @return array{0: CarbonInterface, 1: CarbonInterface}
     */
    public function range(): array
    {
        return match ($this->period) {
            'last_month' => [today()->subMonthNoOverflow()->startOfMonth(), today()->subMonthNoOverflow()->endOfMonth()->startOfDay()],
            'last_30' => [today()->subDays(29), today()],
            'next_30' => [today(), today()->addDays(29)],
            'this_year' => [today()->startOfYear(), today()],
            default => [today()->startOfMonth(), today()->endOfMonth()->startOfDay()],
        };
    }

    public function toman(?int $amount): string
    {
        return $amount === null ? '—' : Number::toPersian(number_format($amount)).' تومان';
    }

    public function percent(?float $value): string
    {
        return $value === null ? '—' : Number::toPersian((string) $value).'٪';
    }
}
