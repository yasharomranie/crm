<?php

namespace App\Domain\Seo;

use App\Enums\SeoKeywordTrend;
use App\Models\SeoKeywordProfile;
use App\Models\SeoProject;
use App\Models\SeoQueryMetric;
use App\Models\SeoQueryPageMetric;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Rolls the warehouse's per-day query rows up into one profile row per
 * keyword (App\Models\SeoKeywordProfile) - what OpportunityEngine and every
 * later report reads from instead of re-aggregating raw daily rows on
 * every page load.
 *
 * Deliberately leaves business_value, topic_cluster_id and
 * cannibalization_risk untouched here: those need either semantic
 * classification (a later AI-backed increment) or OpportunityEngine's own
 * cross-page analysis, not a plain aggregation - a profile field this
 * service can't honestly compute is left null, never guessed.
 */
class KeywordIntelligenceService
{
    private const CURRENT_WINDOW_DAYS = 28;

    private const PREVIOUS_WINDOW_DAYS = 28;

    /** A position swing smaller than this is noise, not a real trend. */
    private const TREND_THRESHOLD = 1.0;

    public function rebuildProfiles(SeoProject $project): void
    {
        $referenceDate = $this->referenceDate($project);

        if (! $referenceDate) {
            return;
        }

        $currentEnd = $referenceDate;
        $currentStart = $currentEnd->clone()->subDays(self::CURRENT_WINDOW_DAYS - 1);
        $previousEnd = $currentStart->clone()->subDay();
        $previousStart = $previousEnd->clone()->subDays(self::PREVIOUS_WINDOW_DAYS - 1);

        $current = $this->aggregateByQuery($project, $currentStart, $currentEnd);
        $previous = $this->aggregateByQuery($project, $previousStart, $previousEnd);
        $extremes = $this->positionExtremesByQuery($project);
        $landingPages = $this->topLandingPageByQuery($project, $currentStart, $currentEnd);

        $now = now();

        $rows = $current->map(function (array $row, string $queryHash) use ($previous, $extremes, $landingPages, $now) {
            $previousRow = $previous->get($queryHash);
            $extremesRow = $extremes->get($queryHash);

            $currentPosition = $row['weighted_position'];
            $previousPosition = $previousRow['weighted_position'] ?? null;

            return [
                'query' => $row['query'],
                'query_hash' => $queryHash,
                'current_position' => $currentPosition,
                'previous_position' => $previousPosition,
                'best_position' => $extremesRow['best'] ?? $currentPosition,
                'worst_position' => $extremesRow['worst'] ?? $currentPosition,
                'clicks_28d' => $row['clicks'],
                'impressions_28d' => $row['impressions'],
                'ctr_28d' => $row['impressions'] > 0 ? round($row['clicks'] / $row['impressions'], 4) : 0,
                'trend' => $this->classifyTrend($currentPosition, $previousPosition)->value,
                'landing_page' => $landingPages->get($queryHash),
                'last_analyzed_at' => $now,
                'updated_at' => $now,
            ];
        });

        collect($rows->values()->all())
            ->map(fn (array $row) => [...$row, 'seo_project_id' => $project->id, 'created_at' => $now])
            ->chunk(500)
            ->each(fn (Collection $chunk) => SeoKeywordProfile::query()->upsert(
                $chunk->all(),
                ['seo_project_id', 'query_hash'],
                [
                    'query', 'current_position', 'previous_position', 'best_position', 'worst_position',
                    'clicks_28d', 'impressions_28d', 'ctr_28d', 'trend', 'landing_page', 'last_analyzed_at', 'updated_at',
                ],
            ));
    }

    private function referenceDate(SeoProject $project): ?Carbon
    {
        $latest = SeoQueryMetric::where('seo_project_id', $project->id)->max('date');

        return $latest ? Carbon::parse($latest) : null;
    }

    /**
     * @return Collection<string, array{query: string, clicks: int, impressions: int, weighted_position: ?float}>
     */
    private function aggregateByQuery(SeoProject $project, Carbon $start, Carbon $end): Collection
    {
        return SeoQueryMetric::where('seo_project_id', $project->id)
            // whereDate(), not whereBetween() with raw date strings: the
            // sync job writes 'date' via a raw upsert() (a plain 'Y-m-d'
            // string, bypassing the model's 'date' cast entirely), while
            // anything going through Eloquent's normal save path - test
            // factories included - serializes it with a time-of-day
            // suffix. whereDate() truncates both sides to the calendar
            // day regardless of which format actually got stored.
            ->whereDate('date', '>=', $start)
            ->whereDate('date', '<=', $end)
            ->selectRaw('query_hash, max(query) as query, sum(clicks) as clicks, sum(impressions) as impressions, sum(position * impressions) as position_weighted_sum')
            ->groupBy('query_hash')
            ->get()
            ->keyBy('query_hash')
            ->map(fn ($row) => [
                'query' => $row->query,
                'clicks' => (int) $row->clicks,
                'impressions' => (int) $row->impressions,
                'weighted_position' => $row->impressions > 0
                    ? round($row->position_weighted_sum / $row->impressions, 2)
                    : null,
            ]);
    }

    /**
     * @return Collection<string, array{best: float, worst: float}>
     */
    private function positionExtremesByQuery(SeoProject $project): Collection
    {
        return SeoQueryMetric::where('seo_project_id', $project->id)
            ->where('impressions', '>', 0)
            ->selectRaw('query_hash, min(position) as best, max(position) as worst')
            ->groupBy('query_hash')
            ->get()
            ->keyBy('query_hash')
            ->map(fn ($row) => ['best' => (float) $row->best, 'worst' => (float) $row->worst]);
    }

    /**
     * The page with the most clicks for each query in the window - done in
     * PHP rather than a portable-SQL "top-1-per-group" query (SQLite/MySQL
     * window function syntax differs enough not to be worth it yet at this
     * data scale; revisit with a window function if a project's query
     * count grows large enough to make this slow).
     *
     * @return Collection<string, string>
     */
    private function topLandingPageByQuery(SeoProject $project, Carbon $start, Carbon $end): Collection
    {
        return SeoQueryPageMetric::where('seo_project_id', $project->id)
            ->whereDate('date', '>=', $start)
            ->whereDate('date', '<=', $end)
            ->selectRaw('query_hash, page_url, sum(clicks) as clicks')
            ->groupBy('query_hash', 'page_url')
            ->get()
            ->groupBy('query_hash')
            ->map(fn (Collection $pages) => $pages->sortByDesc('clicks')->first()->page_url);
    }

    private function classifyTrend(?float $current, ?float $previous): SeoKeywordTrend
    {
        if ($current === null) {
            return SeoKeywordTrend::Stable;
        }

        if ($previous === null) {
            return SeoKeywordTrend::New;
        }

        // Position is "lower is better" (position 1 beats position 10), so
        // a drop in the number is a rise in ranking.
        $delta = $previous - $current;

        return match (true) {
            $delta >= self::TREND_THRESHOLD => SeoKeywordTrend::Rising,
            $delta <= -self::TREND_THRESHOLD => SeoKeywordTrend::Falling,
            default => SeoKeywordTrend::Stable,
        };
    }
}
