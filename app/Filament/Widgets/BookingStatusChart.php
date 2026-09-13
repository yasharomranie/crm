<?php

namespace App\Filament\Widgets;

use App\Enums\BookingStatus;
use App\Models\Booking;
use Filament\Widgets\ChartWidget;

class BookingStatusChart extends ChartWidget
{
    protected ?string $heading = 'وضعیت رزروها';

    protected static ?int $sort = -1;

    protected int|string|array $columnSpan = 1;

    protected function getType(): string
    {
        return 'doughnut';
    }

    /**
     * @return array<string, mixed>
     */
    protected function getData(): array
    {
        $counts = Booking::query()
            ->selectRaw('status, count(*) as booking_count')
            ->groupBy('status')
            ->get()
            ->keyBy(fn ($row) => $row->status->value);

        $statuses = BookingStatus::cases();

        // Same semantic colors BookingStatus::getColor() already uses
        // elsewhere in the app (gray/warning/info/success/danger), so this
        // chart reads consistently with every status badge in the system.
        $colorMap = [
            'gray' => '#9ca3af',
            'warning' => '#f59e0b',
            'info' => '#3b82f6',
            'success' => '#16a34a',
            'danger' => '#dc2626',
        ];

        return [
            'datasets' => [
                [
                    'data' => collect($statuses)
                        ->map(fn (BookingStatus $status) => (int) ($counts[$status->value]->booking_count ?? 0))
                        ->all(),
                    'backgroundColor' => collect($statuses)
                        ->map(fn (BookingStatus $status) => $colorMap[$status->getColor()])
                        ->all(),
                ],
            ],
            'labels' => collect($statuses)->map(fn (BookingStatus $status) => $status->getLabel())->all(),
        ];
    }
}
