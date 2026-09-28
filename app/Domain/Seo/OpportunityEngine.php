<?php

namespace App\Domain\Seo;

use App\Enums\SeoCannibalizationRisk;
use App\Enums\SeoConfidenceLevel;
use App\Enums\SeoOpportunityPriority;
use App\Enums\SeoOpportunityStatus;
use App\Enums\SeoOpportunityType;
use App\Models\SeoDailyMetric;
use App\Models\SeoKeywordProfile;
use App\Models\SeoOpportunity;
use App\Models\SeoPageMetric;
use App\Models\SeoProject;
use App\Models\SeoQueryMetric;
use App\Models\SeoQueryPageMetric;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * The heart of "مرکز سئو": turns the warehouse + keyword profiles into
 * concrete, evidence-backed opportunities instead of raw numbers. Every
 * detector below states its own reasoning in the opportunity's `evidence`
 * and picks a SeoConfidenceLevel that reflects how certain the finding
 * actually is - never High for something inferred from an approximate
 * benchmark, never presented as a confirmed cause without checking it.
 *
 * Run after KeywordIntelligenceService::rebuildProfiles() - the CTR and
 * cannibalization detectors read the profiles it just built, not raw
 * per-day rows.
 */
class OpportunityEngine
{
    /**
     * A rough, static reference CTR curve by position bracket - not
     * scraped from this project's own history (there isn't enough of it
     * yet). Good enough to flag "this is well below what its position
     * typically gets", not precise enough to justify High confidence on
     * its own - see detectCtrOpportunities()'s own confidence choice.
     *
     * @var array<int, array{0: int, 1: int, 2: float}>
     */
    private const CTR_BENCHMARKS = [
        [1, 3, 0.25],
        [4, 6, 0.08],
        [7, 10, 0.03],
        [11, 15, 0.015],
    ];

    private const MIN_IMPRESSIONS_FOR_CTR_CHECK = 100;

    private const TRAFFIC_DROP_MIN_BASELINE_CLICKS = 20;

    private const TRAFFIC_DROP_THRESHOLD = 0.20;

    private const ANOMALY_MIN_HISTORY_DAYS = 14;

    private const ANOMALY_Z_THRESHOLD = 2.0;

    private const CANNIBALIZATION_MIN_IMPRESSIONS = 20;

    public function scan(SeoProject $project): void
    {
        $this->detectCtrOpportunities($project);
        $this->detectTrafficDrop($project);
        $this->detectAnomalies($project);
        $this->detectCannibalization($project);
    }

    /**
     * High impressions + position 5-15 + CTR well below the benchmark for
     * that bracket = the page ranks but isn't winning the click - the
     * classic "fix the title/meta/SERP presentation" opportunity.
     */
    private function detectCtrOpportunities(SeoProject $project): void
    {
        $profiles = SeoKeywordProfile::where('seo_project_id', $project->id)
            ->where('impressions_28d', '>=', self::MIN_IMPRESSIONS_FOR_CTR_CHECK)
            ->whereNotNull('current_position')
            ->whereBetween('current_position', [1, 15])
            ->get();

        foreach ($profiles as $profile) {
            $benchmark = $this->ctrBenchmarkFor((float) $profile->current_position);

            if ($benchmark === null || (float) $profile->ctr_28d >= $benchmark) {
                continue;
            }

            $estimatedMonthlyClicksGained = round($profile->impressions_28d * ($benchmark - (float) $profile->ctr_28d), 2);

            $profile->update(['opportunity_score' => $estimatedMonthlyClicksGained]);

            $this->recordOrRefreshOpportunity(
                project: $project,
                type: SeoOpportunityType::CtrOpportunity,
                subjectQuery: $profile->query,
                subjectPageUrl: $profile->landing_page,
                title: "«{$profile->query}» رتبه دارد اما CTR پایین‌تر از حد انتظار",
                evidence: [
                    'keyword' => $profile->query,
                    'position' => (float) $profile->current_position,
                    'impressions_28d' => $profile->impressions_28d,
                    'clicks_28d' => $profile->clicks_28d,
                    'ctr_28d' => (float) $profile->ctr_28d,
                    'benchmark_ctr_for_position' => $benchmark,
                    'estimated_monthly_clicks_if_fixed' => $estimatedMonthlyClicksGained,
                    'landing_page' => $profile->landing_page,
                ],
                actions: ['review_title', 'review_meta', 'review_search_intent', 'improve_content', 'review_serp'],
                priority: $profile->impressions_28d >= 1000 ? SeoOpportunityPriority::High : SeoOpportunityPriority::Medium,
                confidence: SeoConfidenceLevel::Medium,
            );
        }
    }

    private function ctrBenchmarkFor(float $position): ?float
    {
        foreach (self::CTR_BENCHMARKS as [$min, $max, $expectedCtr]) {
            if ($position >= $min && $position <= $max) {
                return $expectedCtr;
            }
        }

        return null;
    }

    /**
     * Project-wide, not per-keyword: last 7 days of clicks vs the 7 days
     * before, then a causal chain down through query/page to name *which*
     * queries/pages actually drove the drop rather than just reporting the
     * percentage.
     */
    private function detectTrafficDrop(SeoProject $project): void
    {
        $referenceDate = SeoDailyMetric::where('seo_project_id', $project->id)->max('date');

        if (! $referenceDate) {
            return;
        }

        $referenceDate = Carbon::parse($referenceDate);
        $currentStart = $referenceDate->clone()->subDays(6);
        $previousEnd = $currentStart->clone()->subDay();
        $previousStart = $previousEnd->clone()->subDays(6);

        $currentClicks = (int) SeoDailyMetric::where('seo_project_id', $project->id)
            ->whereDate('date', '>=', $currentStart)->whereDate('date', '<=', $referenceDate)
            ->sum('clicks');
        $previousClicks = (int) SeoDailyMetric::where('seo_project_id', $project->id)
            ->whereDate('date', '>=', $previousStart)->whereDate('date', '<=', $previousEnd)
            ->sum('clicks');

        if ($previousClicks < self::TRAFFIC_DROP_MIN_BASELINE_CLICKS) {
            return;
        }

        $percentChange = ($currentClicks - $previousClicks) / $previousClicks;

        if ($percentChange > -self::TRAFFIC_DROP_THRESHOLD) {
            return;
        }

        $topQueries = $this->topDecliningRows(SeoQueryMetric::class, $project, 'query', $currentStart, $referenceDate, $previousStart, $previousEnd);
        $topPages = $this->topDecliningRows(SeoPageMetric::class, $project, 'page_url', $currentStart, $referenceDate, $previousStart, $previousEnd);

        $totalDecline = $previousClicks - $currentClicks;
        $explainedByTopQueries = $topQueries->sum('decline');
        $confidence = $totalDecline > 0 && ($explainedByTopQueries / $totalDecline) >= 0.5
            ? SeoConfidenceLevel::High
            : SeoConfidenceLevel::Medium;

        $this->recordOrRefreshOpportunity(
            project: $project,
            type: SeoOpportunityType::TrafficDrop,
            subjectQuery: null,
            subjectPageUrl: null,
            title: sprintf('افت %d٪ در کلیک‌های ارگانیک (۷ روز اخیر نسبت به ۷ روز قبل)', abs(round($percentChange * 100))),
            evidence: [
                'current_week_clicks' => $currentClicks,
                'previous_week_clicks' => $previousClicks,
                'percent_change' => round($percentChange * 100, 1),
                'top_declining_queries' => $topQueries->values()->all(),
                'top_declining_pages' => $topPages->values()->all(),
            ],
            actions: ['check_technical_issues', 'check_serp_competition', 'check_seasonality', 'review_top_declining_pages'],
            priority: $percentChange <= -0.30 ? SeoOpportunityPriority::High : SeoOpportunityPriority::Medium,
            confidence: $confidence,
        );
    }

    /**
     * @param  class-string<SeoQueryMetric|SeoPageMetric>  $model
     * @return Collection<int, array{label: string, decline: int}>
     */
    private function topDecliningRows(string $model, SeoProject $project, string $labelColumn, Carbon $currentStart, Carbon $currentEnd, Carbon $previousStart, Carbon $previousEnd): Collection
    {
        $current = $model::where('seo_project_id', $project->id)
            ->whereDate('date', '>=', $currentStart)->whereDate('date', '<=', $currentEnd)
            ->selectRaw("{$labelColumn} as label, sum(clicks) as clicks")
            ->groupBy($labelColumn)
            ->pluck('clicks', 'label');

        $previous = $model::where('seo_project_id', $project->id)
            ->whereDate('date', '>=', $previousStart)->whereDate('date', '<=', $previousEnd)
            ->selectRaw("{$labelColumn} as label, sum(clicks) as clicks")
            ->groupBy($labelColumn)
            ->pluck('clicks', 'label');

        return $previous->map(fn ($previousClicks, $label) => [
            'label' => $label,
            'decline' => (int) $previousClicks - (int) ($current->get($label) ?? 0),
        ])
            ->filter(fn (array $row) => $row['decline'] > 0)
            ->sortByDesc('decline')
            ->take(5)
            ->values();
    }

    /**
     * A rolling 28-day mean/std of daily clicks (excluding the day being
     * tested), z-scored - only runs once there's enough history for the
     * mean/std to mean anything, and only flags the single most recent
     * day rather than re-walking the whole window every scan.
     */
    private function detectAnomalies(SeoProject $project): void
    {
        $days = SeoDailyMetric::where('seo_project_id', $project->id)
            ->orderByDesc('date')
            ->limit(29)
            ->get(['date', 'clicks']);

        if ($days->count() < self::ANOMALY_MIN_HISTORY_DAYS + 1) {
            return;
        }

        $latest = $days->first();
        $history = $days->slice(1);

        $mean = $history->avg('clicks');
        $variance = $history->avg(fn ($day) => ($day->clicks - $mean) ** 2);
        $stdDev = sqrt($variance);

        if ($stdDev < 1.0) {
            return;
        }

        $zScore = ($latest->clicks - $mean) / $stdDev;

        if (abs($zScore) < self::ANOMALY_Z_THRESHOLD) {
            return;
        }

        $isPositive = $zScore > 0;
        $latestDate = Carbon::parse($latest->date);

        $this->recordOrRefreshOpportunity(
            project: $project,
            type: $isPositive ? SeoOpportunityType::AnomalyPositive : SeoOpportunityType::AnomalyNegative,
            subjectQuery: null,
            subjectPageUrl: null,
            title: $isPositive
                ? sprintf('رشد غیرعادی کلیک در %s', $latestDate->toDateString())
                : sprintf('افت غیرعادی کلیک در %s', $latestDate->toDateString()),
            evidence: [
                'date' => $latestDate->toDateString(),
                'clicks' => $latest->clicks,
                'expected_range' => [round($mean - 2 * $stdDev, 1), round($mean + 2 * $stdDev, 1)],
                'z_score' => round($zScore, 2),
            ],
            actions: $isPositive
                ? ['identify_growth_driver', 'capitalize_on_trend']
                : ['check_technical_issues', 'check_search_console_manual_actions', 'check_indexing'],
            priority: abs($zScore) >= 3 ? SeoOpportunityPriority::High : SeoOpportunityPriority::Medium,
            confidence: abs($zScore) >= 3 ? SeoConfidenceLevel::High : SeoConfidenceLevel::Medium,
            dedupeDate: $latestDate,
        );
    }

    /**
     * Two-or-more pages meaningfully ranking for the same query. Without
     * real search-intent classification (a later, AI-backed increment)
     * this can't yet tell "different intent, no real problem" apart from
     * true competition - so it only ever reaches Medium confidence, and
     * the distribution of clicks between the pages is what decides
     * "possible" vs "high" risk, not a guess at intent.
     */
    private function detectCannibalization(SeoProject $project): void
    {
        $referenceDate = SeoQueryPageMetric::where('seo_project_id', $project->id)->max('date');

        if (! $referenceDate) {
            return;
        }

        $referenceDate = Carbon::parse($referenceDate);
        $windowStart = $referenceDate->clone()->subDays(27);

        $rows = SeoQueryPageMetric::where('seo_project_id', $project->id)
            ->whereDate('date', '>=', $windowStart)->whereDate('date', '<=', $referenceDate)
            ->selectRaw('query_hash, max(query) as query, page_url, sum(clicks) as clicks, sum(impressions) as impressions')
            ->groupBy('query_hash', 'page_url')
            ->having('impressions', '>=', self::CANNIBALIZATION_MIN_IMPRESSIONS)
            ->get()
            ->groupBy('query_hash');

        foreach ($rows as $queryHash => $pages) {
            if ($pages->count() < 2) {
                $this->updateCannibalizationRisk($project, $queryHash, SeoCannibalizationRisk::None);

                continue;
            }

            $totalClicks = max($pages->sum('clicks'), 1);
            $shares = $pages->map(fn ($row) => [
                'page_url' => $row->page_url,
                'clicks' => (int) $row->clicks,
                'impressions' => (int) $row->impressions,
                'share' => round($row->clicks / $totalClicks, 2),
            ])->sortByDesc('share')->values();

            $topTwoShares = $shares->take(2)->pluck('share');
            $risk = $topTwoShares->count() === 2 && $topTwoShares->every(fn ($share) => $share >= 0.30)
                ? SeoCannibalizationRisk::High
                : SeoCannibalizationRisk::Possible;

            $this->updateCannibalizationRisk($project, $queryHash, $risk);

            $query = $pages->first()->query;

            $this->recordOrRefreshOpportunity(
                project: $project,
                type: SeoOpportunityType::Cannibalization,
                subjectQuery: $query,
                subjectPageUrl: null,
                title: "چند صفحه برای «{$query}» رقابت می‌کنند",
                evidence: ['keyword' => $query, 'pages' => $shares->all()],
                actions: ['choose_primary_page', 'differentiate_or_merge_content', 'update_internal_links_to_primary'],
                priority: $risk === SeoCannibalizationRisk::High ? SeoOpportunityPriority::High : SeoOpportunityPriority::Medium,
                confidence: SeoConfidenceLevel::Medium,
            );
        }
    }

    private function updateCannibalizationRisk(SeoProject $project, string $queryHash, SeoCannibalizationRisk $risk): void
    {
        SeoKeywordProfile::where('seo_project_id', $project->id)
            ->where('query_hash', $queryHash)
            ->update(['cannibalization_risk' => $risk->value]);
    }

    /**
     * @param  array<string, mixed>  $evidence
     * @param  array<int, string>  $actions
     */
    private function recordOrRefreshOpportunity(
        SeoProject $project,
        SeoOpportunityType $type,
        ?string $subjectQuery,
        ?string $subjectPageUrl,
        string $title,
        array $evidence,
        array $actions,
        SeoOpportunityPriority $priority,
        SeoConfidenceLevel $confidence,
        ?Carbon $dedupeDate = null,
    ): void {
        $query = SeoOpportunity::where('seo_project_id', $project->id)
            ->where('type', $type->value)
            ->whereIn('status', [SeoOpportunityStatus::Detected->value, SeoOpportunityStatus::Reviewed->value]);

        if ($dedupeDate) {
            $query->whereDate('detected_at', $dedupeDate);
        } else {
            $query->where('subject_query', $subjectQuery)->where('subject_page_url', $subjectPageUrl);
        }

        $existing = $query->first();

        $attributes = [
            'title' => $title,
            'evidence' => $evidence,
            'recommended_actions' => $actions,
            'priority' => $priority->value,
            'confidence' => $confidence->value,
            'detected_at' => $dedupeDate ?? now(),
        ];

        if ($existing) {
            $existing->update($attributes);

            return;
        }

        SeoOpportunity::create([
            'seo_project_id' => $project->id,
            'type' => $type->value,
            'subject_query' => $subjectQuery,
            'subject_page_url' => $subjectPageUrl,
            'status' => SeoOpportunityStatus::Detected->value,
            ...$attributes,
        ]);
    }
}
