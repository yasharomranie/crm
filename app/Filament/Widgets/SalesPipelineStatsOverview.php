<?php

namespace App\Filament\Widgets;

use App\Enums\DealStage;
use App\Models\Deal;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Number;

/**
 * The sales pipeline's own headline numbers - separate from
 * GanjnamehStatsOverview's single "ارزش پایپ‌لاین باز" line on the main
 * dashboard, this is the fuller CRM-style KPI row (forecast, win rate,
 * average deal size, stale deals) that only makes sense in the context of
 * the pipeline page itself.
 */
class SalesPipelineStatsOverview extends StatsOverviewWidget
{
    protected function getStats(): array
    {
        $openStages = [DealStage::NewLead, DealStage::NeedsAssessment, DealStage::ProposalSent, DealStage::Negotiation];
        $openDeals = Deal::query()->whereIn('stage', $openStages)->get();

        return [
            $this->openPipelineValueStat($openDeals),
            $this->weightedForecastStat($openDeals),
            $this->winRateStat(),
            $this->averageDealValueStat(),
            $this->closedThisMonthStat(),
            $this->staleDealsStat($openDeals),
        ];
    }

    private function openPipelineValueStat(Collection $openDeals): Stat
    {
        $trend = collect(range(6, 0))->map(
            fn (int $weeksAgo) => (int) Deal::query()
                ->whereIn('stage', [DealStage::NewLead, DealStage::NeedsAssessment, DealStage::ProposalSent, DealStage::Negotiation])
                ->where('created_at', '<=', now()->subWeeks($weeksAgo))
                ->sum('value')
        );

        return Stat::make('ارزش باز پایپ‌لاین', Number::toPersian(number_format((int) $openDeals->sum('value'))).' تومان')
            ->description(Number::toPersian($openDeals->count().' معاملهٔ در جریان'))
            ->descriptionIcon(Heroicon::OutlinedBanknotes)
            ->color('primary')
            ->chart($trend->all())
            ->chartColor('primary');
    }

    private function weightedForecastStat(Collection $openDeals): Stat
    {
        $weighted = $openDeals->sum(fn (Deal $deal) => (int) $deal->value * $deal->stage->probability() / 100);

        return Stat::make('پیش‌بینی وزن‌دار فروش', Number::toPersian(number_format((int) $weighted)).' تومان')
            ->description('بر اساس احتمال بستن هر مرحله')
            ->descriptionIcon(Heroicon::OutlinedChartBar)
            ->color('info');
    }

    private function winRateStat(): Stat
    {
        $won = Deal::query()->where('stage', DealStage::Won)->count();
        $lost = Deal::query()->where('stage', DealStage::Lost)->count();
        $decided = $won + $lost;

        $rate = $decided > 0 ? (int) round($won / $decided * 100) : null;

        return Stat::make('نرخ برد', $rate === null ? '—' : Number::toPersian("{$rate}٪"))
            ->description($decided > 0 ? Number::toPersian("{$won} برد از {$decided} معاملهٔ تصمیم‌گیری‌شده") : 'هنوز معامله‌ای بسته نشده')
            ->descriptionIcon(Heroicon::OutlinedTrophy)
            ->color($rate === null ? 'gray' : ($rate >= 50 ? 'success' : 'warning'));
    }

    private function averageDealValueStat(): Stat
    {
        $average = (int) Deal::query()->whereNotNull('value')->avg('value');

        return Stat::make('میانگین ارزش معامله', Number::toPersian(number_format($average)).' تومان')
            ->description('میانگین کل معاملات ثبت‌شده')
            ->descriptionIcon(Heroicon::OutlinedCalculator)
            ->color('gray');
    }

    private function closedThisMonthStat(): Stat
    {
        $wonThisMonth = Deal::query()
            ->where('stage', DealStage::Won)
            ->whereMonth('updated_at', now()->month)
            ->whereYear('updated_at', now()->year)
            ->get();

        return Stat::make('برد این ماه', Number::toPersian(number_format((int) $wonThisMonth->sum('value'))).' تومان')
            ->description(Number::toPersian($wonThisMonth->count().' معاملهٔ قطعی‌شده'))
            ->descriptionIcon(Heroicon::OutlinedCheckBadge)
            ->color('success');
    }

    private function staleDealsStat(Collection $openDeals): Stat
    {
        $threshold = Carbon::now()->subDays(14);
        $stale = $openDeals->where('updated_at', '<=', $threshold)->count();

        return Stat::make('معاملات راکد', Number::toPersian($stale))
            ->description($stale > 0 ? 'بیش از ۱۴ روز بدون پیگیری' : 'همه معاملات به‌روز هستند')
            ->descriptionIcon($stale > 0 ? Heroicon::OutlinedExclamationTriangle : Heroicon::OutlinedCheckCircle)
            ->descriptionColor($stale > 0 ? 'danger' : 'success')
            ->color($stale > 0 ? 'danger' : 'success');
    }
}
