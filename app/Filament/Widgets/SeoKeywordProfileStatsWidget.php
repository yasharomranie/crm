<?php

namespace App\Filament\Widgets;

use App\Enums\SeoKeywordTrend;
use App\Enums\SeoSearchIntent;
use App\Models\SeoKeywordProfile;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Number;

/**
 * «کلمات کلیدی»'s own headline numbers, aggregated across every tracked
 * keyword profile (all SEO Projects) - answers "how is our keyword
 * portfolio doing overall" before scrolling into the row-by-row table
 * below it.
 */
class SeoKeywordProfileStatsWidget extends StatsOverviewWidget
{
    protected function getStats(): array
    {
        return [
            $this->clicksStat(),
            $this->impressionsStat(),
            $this->topFiveStat(),
            $this->trendStat(SeoKeywordTrend::Rising, 'کلمات در حال رشد', Heroicon::OutlinedArrowTrendingUp, 'success'),
            $this->trendStat(SeoKeywordTrend::Falling, 'کلمات در حال افت', Heroicon::OutlinedArrowTrendingDown, 'danger'),
            $this->trendStat(SeoKeywordTrend::Stable, 'کلمات ثابت', Heroicon::OutlinedMinus, 'gray'),
            $this->localIntentStat(),
        ];
    }

    private function clicksStat(): Stat
    {
        $clicks = (int) SeoKeywordProfile::query()->sum('clicks_28d');

        return Stat::make('تعداد کلیک سایت', Number::toPersian(number_format($clicks)))
            ->description('مجموع کلیک ۲۸ روز اخیر، همه‌ی کلمات کلیدی')
            ->descriptionIcon(Heroicon::OutlinedCursorArrowRays)
            ->color('primary');
    }

    private function impressionsStat(): Stat
    {
        $impressions = (int) SeoKeywordProfile::query()->sum('impressions_28d');

        return Stat::make('تعداد نمایش', Number::toPersian(number_format($impressions)))
            ->description('مجموع نمایش ۲۸ روز اخیر، همه‌ی کلمات کلیدی')
            ->descriptionIcon(Heroicon::OutlinedEye)
            ->color('info');
    }

    private function topFiveStat(): Stat
    {
        $count = SeoKeywordProfile::query()
            ->whereNotNull('current_position')
            ->whereBetween('current_position', [1, 5])
            ->count();

        return Stat::make('کلمات با رتبه ۱ تا ۵', Number::toPersian($count))
            ->descriptionIcon(Heroicon::OutlinedTrophy)
            ->color('success');
    }

    private function trendStat(SeoKeywordTrend $trend, string $label, Heroicon $icon, string $color): Stat
    {
        $count = SeoKeywordProfile::query()->where('trend', $trend)->count();

        return Stat::make($label, Number::toPersian($count))
            ->descriptionIcon($icon)
            ->color($color);
    }

    /**
     * Local SEO Layer 1's one piece of keyword visibility - how many
     * already-classified keywords have local intent ("نزدیک من",
     * "در همدان" و...) and how much click volume they're pulling in,
     * reusing the AI intent classification that already runs on sync
     * rather than adding a second classifier for the same data.
     */
    private function localIntentStat(): Stat
    {
        $query = SeoKeywordProfile::query()->where('intent', SeoSearchIntent::Local);
        $count = $query->count();
        $clicks = (int) $query->sum('clicks_28d');

        return Stat::make('کلمات با نیت محلی', Number::toPersian($count))
            ->description(sprintf('%s کلیک ۲۸ روز اخیر', Number::toPersian(number_format($clicks))))
            ->descriptionIcon(Heroicon::OutlinedMapPin)
            ->color('primary');
    }
}
