<?php

namespace App\Filament\Pages;

use App\Enums\DealStage;
use App\Filament\Pages\Concerns\HasAccessControlledPage;
use App\Filament\Widgets\DealSourceChart;
use App\Filament\Widgets\DealWinLossTrendChart;
use App\Filament\Widgets\SalesFunnelChart;
use App\Filament\Widgets\SalesPipelineStatsOverview;
use App\Filament\Widgets\StaleDealsWidget;
use App\Filament\Widgets\TopSalesRepsWidget;
use App\Models\Deal;
use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Collection;

class SalesPipeline extends Page
{
    use HasAccessControlledPage;

    protected string $view = 'filament.pages.sales-pipeline';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedViewColumns;

    protected static string|\UnitEnum|null $navigationGroup = 'CRM و مشتریان';

    protected static ?string $navigationLabel = 'پایپ‌لاین فروش (کانبان)';

    protected static ?string $title = 'پایپ‌لاین فروش';

    public static function accessControlPermission(): string
    {
        return 'sales-pipeline.access';
    }

    /**
     * The pipeline's own report row - KPIs, stage distribution, source
     * breakdown, win/loss trend, a leaderboard, and a stale-deals list -
     * kept off the main dashboard by App\Filament\Pages\Dashboard, which
     * excludes these same widget classes from its own widget list.
     *
     * @return array<class-string>
     */
    protected function getHeaderWidgets(): array
    {
        return [
            SalesPipelineStatsOverview::class,
            SalesFunnelChart::class,
            DealSourceChart::class,
            DealWinLossTrendChart::class,
            TopSalesRepsWidget::class,
            StaleDealsWidget::class,
        ];
    }

    /**
     * @return array<string, Collection<int, Deal>>
     */
    public function getColumns(): array
    {
        $deals = Deal::query()->with(['guest', 'assignee'])->latest()->get();

        // Note: ->all() here, not ->toArray() - the latter recursively
        // converts each stage's Collection into a plain array too, which
        // then breaks ->count()/iteration helpers used in the view.
        return collect(DealStage::pipelineOrder())
            ->mapWithKeys(fn (DealStage $stage) => [
                $stage->value => $deals->where('stage', $stage),
            ])
            ->all();
    }

    public function moveStage(int $dealId, string $direction): void
    {
        $deal = Deal::findOrFail($dealId);
        $order = DealStage::pipelineOrder();
        $currentIndex = array_search($deal->stage, $order, true);
        $newIndex = $direction === 'next' ? $currentIndex + 1 : $currentIndex - 1;

        if (! isset($order[$newIndex])) {
            return;
        }

        $this->moveDealToStage($dealId, $order[$newIndex]->value);
    }

    /**
     * Called by the kanban board's drag-and-drop (see sales-pipeline.blade.php's
     * Sortable.js wiring) when a card is dropped on a different column.
     */
    public function moveDealToStage(int $dealId, string $stageValue): void
    {
        $stage = DealStage::tryFrom($stageValue);

        if (! $stage) {
            return;
        }

        $deal = Deal::findOrFail($dealId);

        if ($deal->stage === $stage) {
            return;
        }

        $deal->update(['stage' => $stage]);

        Notification::make()
            ->title("معامله به مرحله «{$stage->getLabel()}» منتقل شد")
            ->success()
            ->send();
    }
}
