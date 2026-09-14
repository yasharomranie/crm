<?php

namespace App\Filament\Widgets;

use App\Enums\DealSource;
use App\Models\Deal;
use Filament\Widgets\ChartWidget;

class DealSourceChart extends ChartWidget
{
    protected ?string $heading = 'منبع سرنخ‌ها';

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
        $counts = Deal::query()
            ->selectRaw('source, count(*) as deal_count')
            ->groupBy('source')
            ->get()
            ->keyBy(fn (Deal $row) => $row->source->value);

        $sources = DealSource::cases();

        // A warm, distinct palette per source - not tied to the semantic
        // status colors (gray/warning/info/success/danger) used elsewhere,
        // since "source" isn't a status with a good/bad connotation.
        $colorMap = [
            'walk_in' => '#0ea5e9',
            'phone' => '#8b5cf6',
            'online' => '#16a34a',
            'referral' => '#f59e0b',
            'social_media' => '#ec4899',
            'other' => '#9ca3af',
        ];

        return [
            'datasets' => [
                [
                    'data' => collect($sources)->map(fn (DealSource $source) => (int) ($counts[$source->value]->deal_count ?? 0))->all(),
                    'backgroundColor' => collect($sources)->map(fn (DealSource $source) => $colorMap[$source->value])->all(),
                ],
            ],
            'labels' => collect($sources)->map(fn (DealSource $source) => $source->getLabel())->all(),
        ];
    }
}
