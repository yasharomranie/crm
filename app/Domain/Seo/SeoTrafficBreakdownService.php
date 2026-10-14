<?php

namespace App\Domain\Seo;

use App\Models\SeoCountryMetric;
use App\Models\SeoProject;
use App\Models\SeoSearchAppearanceMetric;
use App\Models\SeoSearchTypeMetric;
use Illuminate\Support\Collection;

/**
 * SyncSearchConsoleDataJob has synced SeoCountryMetric/SeoSearchTypeMetric/
 * SeoSearchAppearanceMetric every day since this app's early sync jobs -
 * real, free Search Console signal (which countries actually visit, how
 * much comes from image/video/Discover search, which rich-result types
 * show up) that no Filament resource or widget ever reads. This is the
 * first thing that reads them.
 */
class SeoTrafficBreakdownService
{
    /** Same trailing-window convention the rest of مرکز سئو uses for "recent" Search Console numbers. */
    private const WINDOW_DAYS = 28;

    /**
     * @return Collection<int, array{country: string, clicks: int, impressions: int}>
     */
    public function topCountries(SeoProject $project, int $limit = 10): Collection
    {
        return SeoCountryMetric::where('seo_project_id', $project->id)
            ->where('date', '>=', now()->subDays(self::WINDOW_DAYS)->toDateString())
            ->selectRaw('country, sum(clicks) as clicks, sum(impressions) as impressions')
            ->groupBy('country')
            ->orderByDesc('clicks')
            ->limit($limit)
            ->get()
            ->map(fn (SeoCountryMetric $row) => [
                'country' => strtoupper($row->country),
                'clicks' => (int) $row->clicks,
                'impressions' => (int) $row->impressions,
            ]);
    }

    /**
     * @return Collection<int, array{search_type: string, clicks: int, impressions: int}>
     */
    public function bySearchType(SeoProject $project): Collection
    {
        return SeoSearchTypeMetric::where('seo_project_id', $project->id)
            ->where('date', '>=', now()->subDays(self::WINDOW_DAYS)->toDateString())
            ->selectRaw('search_type, sum(clicks) as clicks, sum(impressions) as impressions')
            ->groupBy('search_type')
            ->orderByDesc('clicks')
            ->get()
            ->map(fn (SeoSearchTypeMetric $row) => [
                'search_type' => $row->search_type->value,
                'clicks' => (int) $row->clicks,
                'impressions' => (int) $row->impressions,
            ]);
    }

    /**
     * Each row already represents the whole sync window as of its own
     * date (see SyncSearchConsoleDataJob::syncSearchAppearance()'s own
     * comment - Google rejects combining this dimension with 'date', so
     * there is no daily breakdown to sum here) - only the latest sync's
     * rows are meaningful, not a sum across several dates.
     *
     * @return Collection<int, array{search_appearance: string, clicks: int, impressions: int}>
     */
    public function bySearchAppearance(SeoProject $project): Collection
    {
        $latestDate = SeoSearchAppearanceMetric::where('seo_project_id', $project->id)->max('date');

        if (! $latestDate) {
            return collect();
        }

        return SeoSearchAppearanceMetric::where('seo_project_id', $project->id)
            ->where('date', $latestDate)
            ->orderByDesc('clicks')
            ->get()
            ->map(fn (SeoSearchAppearanceMetric $row) => [
                'search_appearance' => $row->search_appearance,
                'clicks' => (int) $row->clicks,
                'impressions' => (int) $row->impressions,
            ]);
    }
}
