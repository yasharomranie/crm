<?php

namespace App\Filament\Widgets;

use App\Enums\BookingStatus;
use App\Models\BookingCharge;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Carbon;
use Morilog\Jalali\Jalalian;

class RevenueTrendChart extends ChartWidget
{
    protected ?string $heading = 'روند درآمد اقامتی (۱۴ روز اخیر)';

    protected static ?int $sort = -1;

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
        $days = collect(range(13, 0))->map(fn (int $daysAgo) => today()->subDays($daysAgo));

        $chargesByDay = BookingCharge::query()
            ->whereHas('booking', fn ($query) => $query->where('status', BookingStatus::CheckedOut)
                ->whereBetween('actual_check_out_at', [$days->first()->copy()->startOfDay(), $days->last()->copy()->endOfDay()]))
            ->with('booking')
            ->get()
            ->groupBy(fn (BookingCharge $charge) => $charge->booking->actual_check_out_at->toDateString());

        $values = $days->map(fn (Carbon $day) => $chargesByDay->get($day->toDateString(), collect())
            ->sum(fn (BookingCharge $charge) => $charge->quantity * $charge->unit_amount));

        return [
            'datasets' => [
                [
                    'label' => 'درآمد (تومان)',
                    'data' => $values->all(),
                    'borderColor' => '#d97706',
                    'backgroundColor' => 'rgba(217, 119, 6, 0.15)',
                    'fill' => true,
                    'tension' => 0.35,
                ],
            ],
            'labels' => $days->map(fn (Carbon $day) => Jalalian::fromCarbon($day)->format('%d/%m'))->all(),
        ];
    }
}
