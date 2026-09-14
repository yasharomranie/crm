<?php

namespace App\Filament\Widgets;

use App\Enums\DealStage;
use App\Filament\Widgets\Concerns\OnlyVisibleOnSalesPipeline;
use App\Models\Deal;
use Filament\Widgets\ChartWidget;

/**
 * A horizontal bar, not a true funnel: deals in this CRM don't decrease
 * monotonically stage-to-stage (a deal can sit in any column, and the board
 * has no stage-change history to compute real drop-off), so this reads as
 * "how many open deals are sitting in each stage right now" rather than a
 * historical conversion funnel - an honest snapshot instead of a chart that
 * implies data we don't have.
 */
class SalesFunnelChart extends ChartWidget
{
    use OnlyVisibleOnSalesPipeline;

    protected ?string $heading = 'توزیع معاملات در پایپ‌لاین';

    protected ?string $description = 'تعداد و ارزش معاملات باز به تفکیک مرحله';

    protected int|string|array $columnSpan = 'full';

    protected function getType(): string
    {
        return 'bar';
    }

    /**
     * @return array<string, mixed>
     */
    protected function getData(): array
    {
        $stages = DealStage::pipelineOrder();

        $counts = Deal::query()
            ->selectRaw('stage, count(*) as deal_count, coalesce(sum(value), 0) as total_value')
            ->groupBy('stage')
            ->get()
            ->keyBy(fn (Deal $row) => $row->stage->value);

        $colorMap = [
            'gray' => '#9ca3af',
            'info' => '#3b82f6',
            'warning' => '#f59e0b',
            'success' => '#16a34a',
            'danger' => '#dc2626',
        ];

        return [
            'datasets' => [
                [
                    'label' => 'تعداد معامله',
                    'data' => collect($stages)->map(fn (DealStage $stage) => (int) ($counts[$stage->value]->deal_count ?? 0))->all(),
                    'backgroundColor' => collect($stages)->map(fn (DealStage $stage) => $colorMap[$stage->getColor()])->all(),
                    'borderRadius' => 6,
                ],
            ],
            'labels' => collect($stages)->map(fn (DealStage $stage) => $stage->getLabel())->all(),
        ];
    }

    protected function getOptions(): array
    {
        return [
            'indexAxis' => 'y',
            'plugins' => [
                'legend' => ['display' => false],
            ],
            'scales' => [
                'x' => ['ticks' => ['precision' => 0]],
            ],
        ];
    }
}
