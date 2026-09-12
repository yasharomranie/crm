<?php

namespace App\Filament\Pages;

use App\Enums\DealStage;
use App\Models\Deal;
use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Collection;

class SalesPipeline extends Page
{
    protected string $view = 'filament.pages.sales-pipeline';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedViewColumns;

    protected static string|\UnitEnum|null $navigationGroup = 'CRM و مشتریان';

    protected static ?string $navigationLabel = 'پایپ‌لاین فروش (کانبان)';

    protected static ?string $title = 'پایپ‌لاین فروش';

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

        $deal->update(['stage' => $order[$newIndex]]);

        Notification::make()
            ->title("معامله به مرحله «{$order[$newIndex]->getLabel()}» منتقل شد")
            ->success()
            ->send();
    }
}
