<?php

namespace App\Filament\Widgets;

use App\Enums\DealStage;
use App\Models\Deal;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Carbon;
use Morilog\Jalali\CalendarUtils;
use Morilog\Jalali\Jalalian;

/**
 * Deals don't carry a "closed_at" timestamp of their own - a deal's
 * updated_at is the closest proxy for "when it landed on won/lost", exactly
 * like RevenueTrendChart uses a booking's own timestamp for its trend.
 */
class DealWinLossTrendChart extends ChartWidget
{
    protected ?string $heading = 'روند برد و باخت (۸ هفتهٔ اخیر)';

    protected int|string|array $columnSpan = 1;

    protected function getType(): string
    {
        return 'line';
    }

    /**
     * @return array<string, mixed>
     */
    protected function getData(): array
    {
        $weeks = collect(range(7, 0))->map(fn (int $weeksAgo) => today()->subWeeks($weeksAgo)->startOfWeek());

        $closed = Deal::query()
            ->whereIn('stage', [DealStage::Won, DealStage::Lost])
            ->where('updated_at', '>=', $weeks->first())
            ->get();

        $won = $weeks->map(fn (Carbon $week) => (int) $closed
            ->where('stage', DealStage::Won)
            ->filter(fn (Deal $deal) => $deal->updated_at->between($week, $week->copy()->endOfWeek()))
            ->sum('value'));

        $lost = $weeks->map(fn (Carbon $week) => (int) $closed
            ->where('stage', DealStage::Lost)
            ->filter(fn (Deal $deal) => $deal->updated_at->between($week, $week->copy()->endOfWeek()))
            ->sum('value'));

        return [
            'datasets' => [
                [
                    'label' => 'ارزش معاملات برد',
                    'data' => $won->all(),
                    'borderColor' => '#16a34a',
                    'backgroundColor' => 'rgba(22, 163, 74, 0.15)',
                    'fill' => true,
                    'tension' => 0.35,
                ],
                [
                    'label' => 'ارزش معاملات باخت',
                    'data' => $lost->all(),
                    'borderColor' => '#dc2626',
                    'backgroundColor' => 'rgba(220, 38, 38, 0.1)',
                    'fill' => true,
                    'tension' => 0.35,
                ],
            ],
            'labels' => $weeks->map(fn (Carbon $week) => CalendarUtils::convertNumbers(Jalalian::fromCarbon($week)->format('%d/%m')))->all(),
        ];
    }

    /**
     * See RevenueTrendChart::getOptions() - same Chart.js locale hook for
     * Persian-digit ticks/tooltips.
     */
    protected function getOptions(): array
    {
        return [
            'locale' => 'fa',
        ];
    }
}
