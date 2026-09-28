<?php

namespace App\Filament\Widgets;

use App\Enums\SeoContentItemStatus;
use App\Enums\SeoKeywordSuggestionStatus;
use App\Enums\SeoOpportunityOutcomeVerdict;
use App\Enums\SeoOpportunityStatus;
use App\Models\SeoContentItem;
use App\Models\SeoKeywordProfile;
use App\Models\SeoKeywordSuggestion;
use App\Models\SeoOpportunity;
use App\Models\SeoOpportunityOutcome;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Number;

/**
 * "داشبورد سئو"'s own headline numbers, aggregated across every SEO
 * Project - a single-glance answer to "is مرکز سئو actually working" for
 * the CEO/SEO staff audience this module was built for, tying together
 * every phase: open opportunities (detection), pending suggestions
 * (intelligence), content due (action), and the success rate (learning,
 * see App\Domain\Seo\OpportunityOutcomeService).
 */
class SeoOverviewStatsWidget extends StatsOverviewWidget
{
    protected function getStats(): array
    {
        return [
            $this->openOpportunitiesStat(),
            $this->resolvedThisMonthStat(),
            $this->successRateStat(),
            $this->trackedKeywordsStat(),
            $this->pendingSuggestionsStat(),
            $this->contentDueThisWeekStat(),
        ];
    }

    private function openOpportunitiesStat(): Stat
    {
        $open = SeoOpportunity::query()
            ->whereIn('status', [SeoOpportunityStatus::Detected, SeoOpportunityStatus::Reviewed])
            ->count();

        return Stat::make('فرصت‌های باز', Number::toPersian($open))
            ->description('نیاز به بررسی یا اقدام دارند')
            ->descriptionIcon(Heroicon::OutlinedLightBulb)
            ->color($open > 0 ? 'warning' : 'success');
    }

    private function resolvedThisMonthStat(): Stat
    {
        $resolved = SeoOpportunity::query()
            ->where('status', SeoOpportunityStatus::Resolved)
            ->whereMonth('resolved_at', now()->month)
            ->whereYear('resolved_at', now()->year)
            ->count();

        return Stat::make('برطرف‌شده این ماه', Number::toPersian($resolved))
            ->descriptionIcon(Heroicon::OutlinedCheckCircle)
            ->color('success');
    }

    private function successRateStat(): Stat
    {
        $decided = SeoOpportunityOutcome::query()->whereNotNull('followup_measured_at')->get();
        $improved = $decided->where('verdict', SeoOpportunityOutcomeVerdict::Improved)->count();
        $decidedCount = $decided->count();

        $rate = $decidedCount > 0 ? (int) round($improved / $decidedCount * 100) : null;

        return Stat::make('نرخ موفقیت اقدامات', $rate === null ? '—' : Number::toPersian("{$rate}٪"))
            ->description($decidedCount > 0
                ? Number::toPersian("{$improved} بهبود از {$decidedCount} اقدام ارزیابی‌شده")
                : 'هنوز اقدامی ارزیابی نشده')
            ->descriptionIcon(Heroicon::OutlinedTrophy)
            ->color($rate === null ? 'gray' : ($rate >= 50 ? 'success' : 'warning'));
    }

    private function trackedKeywordsStat(): Stat
    {
        return Stat::make('کلمات کلیدی ردیابی‌شده', Number::toPersian(SeoKeywordProfile::query()->count()))
            ->descriptionIcon(Heroicon::OutlinedMagnifyingGlass)
            ->color('primary');
    }

    private function pendingSuggestionsStat(): Stat
    {
        $pending = SeoKeywordSuggestion::query()->where('status', SeoKeywordSuggestionStatus::New)->count();

        return Stat::make('پیشنهادهای در انتظار تأیید', Number::toPersian($pending))
            ->descriptionIcon(Heroicon::OutlinedSparkles)
            ->color($pending > 0 ? 'info' : 'gray');
    }

    private function contentDueThisWeekStat(): Stat
    {
        $due = SeoContentItem::query()
            ->where('status', '!=', SeoContentItemStatus::Published)
            ->whereNotNull('due_date')
            ->whereDate('due_date', '<=', now()->addDays(7)->toDateString())
            ->count();

        return Stat::make('محتوای نزدیک به مهلت', Number::toPersian($due))
            ->description('سررسیده یا تا ۷ روز آینده')
            ->descriptionIcon(Heroicon::OutlinedCalendarDays)
            ->color($due > 0 ? 'warning' : 'success');
    }
}
