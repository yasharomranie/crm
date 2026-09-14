<?php

namespace App\Filament\Widgets;

use App\Enums\DealStage;
use App\Filament\Widgets\Concerns\OnlyVisibleOnSalesPipeline;
use App\Models\Deal;
use Filament\Widgets\Widget;

/**
 * A leaderboard of who's actually closing deals - richer than a bar chart
 * (avatar initials, a relative progress bar, won-count alongside value) so
 * the pipeline page reads like a real sales report, not just a kanban
 * board.
 */
class TopSalesRepsWidget extends Widget
{
    use OnlyVisibleOnSalesPipeline;

    protected string $view = 'filament.widgets.top-sales-reps';

    protected int|string|array $columnSpan = 1;

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        $reps = Deal::query()
            ->where('stage', DealStage::Won)
            ->whereNotNull('assigned_to')
            ->with('assignee')
            ->get()
            ->groupBy('assigned_to')
            ->map(fn ($deals) => [
                'user' => $deals->first()->assignee,
                'won_count' => $deals->count(),
                'won_value' => (int) $deals->sum('value'),
            ])
            ->sortByDesc('won_value')
            ->take(5)
            ->values();

        $topValue = (int) ($reps->first()['won_value'] ?? 0);

        return [
            'reps' => $reps,
            'topValue' => max($topValue, 1),
        ];
    }
}
