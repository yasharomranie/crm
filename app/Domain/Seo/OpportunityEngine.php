<?php

namespace App\Domain\Seo;

use App\Enums\SeoCannibalizationRisk;
use App\Enums\SeoConfidenceLevel;
use App\Enums\SeoContentItemStatus;
use App\Enums\SeoOpportunityPriority;
use App\Enums\SeoOpportunityStatus;
use App\Enums\SeoOpportunityType;
use App\Enums\SeoTechnicalAuditStatus;
use App\Models\SeoContentItem;
use App\Models\SeoDailyMetric;
use App\Models\SeoInternalLink;
use App\Models\SeoKeywordProfile;
use App\Models\SeoOpportunity;
use App\Models\SeoPageMetric;
use App\Models\SeoProject;
use App\Models\SeoQueryMetric;
use App\Models\SeoQueryPageMetric;
use App\Models\SeoSitemapEntry;
use App\Models\SeoTechnicalAudit;
use App\Models\SeoTopicCluster;
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

    /**
     * Per-page/per-query anomaly checks are scoped to only this many of
     * the project's busiest subjects (by recent click volume) - checking
     * every single page/query would be slow and, for the long tail of
     * near-zero-traffic ones, pure noise rather than a useful signal.
     */
    private const ANOMALY_CANDIDATE_POOL_SIZE = 15;

    private const CANNIBALIZATION_MIN_IMPRESSIONS = 20;

    /** "Page two" by the usual SEO-practitioner definition - close enough to page one to be worth a deliberate push, not a long-shot. */
    private const STRIKING_DISTANCE_MIN_POSITION = 11;

    private const STRIKING_DISTANCE_MAX_POSITION = 20;

    private const STRIKING_DISTANCE_MIN_IMPRESSIONS = 20;

    /** A keyword's best-ever position at or above this is "has been on page one" - losing that is a genuine regression, not just normal rank noise. */
    private const PAGE_ONE_THRESHOLD = 10;

    private const CLUSTER_CANNIBALIZATION_MIN_IMPRESSIONS = 20;

    /** 52 weeks back, not a flat 365 - keeps the same day-of-week alignment the current/previous-week comparison above already relies on. */
    private const YEAR_OVER_YEAR_DAYS = 364;

    /** A published page earning less than this fraction of its own topic cluster's average clicks is underperforming its peers, not just naturally quieter. */
    private const UNDERPERFORMING_CONTENT_RATIO = 0.3;

    /** A cluster of just one page has no peer to link from or to - needs at least this many sitemap entries in the same cluster before "missing a link from a cluster peer" means anything. */
    private const MIN_CLUSTER_SIZE_FOR_LINK_SUGGESTION = 2;

    public function __construct(
        private readonly SeoAlertService $alerts,
        private readonly ContentPerformanceService $contentPerformance,
    ) {}

    public function scan(SeoProject $project): void
    {
        $this->detectCtrOpportunities($project);
        $this->detectStrikingDistanceKeywords($project);
        $this->detectPageOneRegressions($project);
        $this->detectTrafficDrop($project);
        $this->detectAnomalies($project);
        $this->detectPageAnomalies($project);
        $this->detectQueryAnomalies($project);
        $this->detectCannibalization($project);
        $this->detectClusterCannibalization($project);
        $this->detectUnderperformingContent($project);
        $this->detectMissingInternalLinksByCluster($project);
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
     * "Page two" keywords - ranking just outside the top 10 with real
     * impressions already, the classic low-effort "push it onto page
     * one" opportunity. Deliberately overlaps the CTR detector's own
     * 1-15 range in positions 11-15: the two recommend different fixes
     * (CTR: fix the title/meta; this: more content/internal links/
     * backlinks), so a keyword legitimately flagged by both is useful,
     * not noisy.
     */
    private function detectStrikingDistanceKeywords(SeoProject $project): void
    {
        $profiles = SeoKeywordProfile::where('seo_project_id', $project->id)
            ->where('impressions_28d', '>=', self::STRIKING_DISTANCE_MIN_IMPRESSIONS)
            ->whereNotNull('current_position')
            ->whereBetween('current_position', [self::STRIKING_DISTANCE_MIN_POSITION, self::STRIKING_DISTANCE_MAX_POSITION])
            ->get();

        foreach ($profiles as $profile) {
            $this->recordOrRefreshOpportunity(
                project: $project,
                type: SeoOpportunityType::StrikingDistance,
                subjectQuery: $profile->query,
                subjectPageUrl: $profile->landing_page,
                title: "«{$profile->query}» در آستانه‌ی صفحه‌ی اول است (رتبه‌ی فعلی {$profile->current_position})",
                evidence: [
                    'keyword' => $profile->query,
                    'position' => (float) $profile->current_position,
                    'impressions_28d' => $profile->impressions_28d,
                    'clicks_28d' => $profile->clicks_28d,
                    'landing_page' => $profile->landing_page,
                ],
                actions: ['improve_content', 'review_internal_links', 'check_backlinks'],
                priority: $profile->impressions_28d >= 500 ? SeoOpportunityPriority::High : SeoOpportunityPriority::Medium,
                confidence: SeoConfidenceLevel::Medium,
            );
        }
    }

    /**
     * A keyword that has genuinely been on page one before
     * (best_position, which KeywordIntelligenceService tracks
     * separately from the current rolling window) and has now fallen
     * past it is a real regression worth more attention than a keyword
     * that simply never reached page one - the two are easy to conflate
     * from current_position alone.
     */
    private function detectPageOneRegressions(SeoProject $project): void
    {
        $profiles = SeoKeywordProfile::where('seo_project_id', $project->id)
            ->where('impressions_28d', '>=', self::STRIKING_DISTANCE_MIN_IMPRESSIONS)
            ->whereNotNull('current_position')
            ->whereNotNull('best_position')
            ->where('best_position', '<=', self::PAGE_ONE_THRESHOLD)
            ->where('current_position', '>', self::PAGE_ONE_THRESHOLD)
            ->get();

        foreach ($profiles as $profile) {
            $this->recordOrRefreshOpportunity(
                project: $project,
                type: SeoOpportunityType::PageOneRegression,
                subjectQuery: $profile->query,
                subjectPageUrl: $profile->landing_page,
                title: "«{$profile->query}» از صفحه‌ی اول گوگل خارج شده (بهترین رتبه {$profile->best_position}، اکنون {$profile->current_position})",
                evidence: [
                    'keyword' => $profile->query,
                    'best_position' => (float) $profile->best_position,
                    'position' => (float) $profile->current_position,
                    'impressions_28d' => $profile->impressions_28d,
                    'clicks_28d' => $profile->clicks_28d,
                    'landing_page' => $profile->landing_page,
                ],
                actions: ['check_technical_issues', 'check_serp_competition', 'check_backlinks', 'review_content_relevance'],
                priority: $profile->impressions_28d >= 500 ? SeoOpportunityPriority::High : SeoOpportunityPriority::Medium,
                confidence: SeoConfidenceLevel::Medium,
            );
        }
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

        // Context only, never a confidence/priority input on its own - a
        // business this seasonal (Hamedan tourism) can show the exact
        // same week-over-week drop every single year around the same
        // date, and a reader needs to see that to tell a real problem
        // apart from the normal seasonal rhythm.
        $lastYearClicks = (int) SeoDailyMetric::where('seo_project_id', $project->id)
            ->whereDate('date', '>=', $currentStart->clone()->subDays(self::YEAR_OVER_YEAR_DAYS))
            ->whereDate('date', '<=', $referenceDate->clone()->subDays(self::YEAR_OVER_YEAR_DAYS))
            ->sum('clicks');

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
                'clicks_same_week_last_year' => $lastYearClicks,
                'percent_change_vs_last_year' => $lastYearClicks > 0 ? round((($currentClicks - $lastYearClicks) / $lastYearClicks) * 100, 1) : null,
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
     * A rolling 28-day mean/std of each site-wide daily metric (excluding
     * the day being tested), z-scored - only runs once there's enough
     * history for the mean/std to mean anything, and only flags the
     * single most recent day rather than re-walking the whole window
     * every scan. Checks four metrics independently, not just clicks: a
     * de-indexing issue shows up in impressions before clicks ever move,
     * a sudden ranking problem shows up in average position, and a
     * weaker SERP listing (lost rich result, a worse title) shows up in
     * CTR while position itself stays flat - each tells apart a
     * different root cause from the same "something's wrong" signal.
     */
    private function detectAnomalies(SeoProject $project): void
    {
        $days = SeoDailyMetric::where('seo_project_id', $project->id)
            ->orderByDesc('date')
            ->limit(self::ANOMALY_MIN_HISTORY_DAYS + 1)
            ->get(['date', 'clicks', 'impressions', 'position', 'ctr']);

        if ($days->count() < self::ANOMALY_MIN_HISTORY_DAYS + 1) {
            return;
        }

        $latest = $days->first();
        $history = $days->slice(1);
        $latestDate = Carbon::parse($latest->date);

        $this->recordSiteWideAnomaly(
            $project, $latestDate, SeoOpportunityType::AnomalyPositive, SeoOpportunityType::AnomalyNegative,
            'کلیک', (float) $latest->clicks, $history->map(fn ($day) => (float) $day->clicks),
            minStdDev: 1.0, higherIsBetter: true,
            actionsPositive: ['identify_growth_driver', 'capitalize_on_trend'],
            actionsNegative: ['check_technical_issues', 'check_search_console_manual_actions', 'check_indexing'],
        );

        $this->recordSiteWideAnomaly(
            $project, $latestDate, SeoOpportunityType::ImpressionAnomalyPositive, SeoOpportunityType::ImpressionAnomalyNegative,
            'ایمپرشن', (float) $latest->impressions, $history->map(fn ($day) => (float) $day->impressions),
            minStdDev: 5.0, higherIsBetter: true,
            actionsPositive: ['identify_growth_driver'],
            actionsNegative: ['check_indexing', 'check_search_console_manual_actions', 'check_robots_and_sitemap'],
        );

        $this->recordSiteWideAnomaly(
            $project, $latestDate, SeoOpportunityType::PositionAnomalyPositive, SeoOpportunityType::PositionAnomalyNegative,
            'میانگین رتبه', (float) $latest->position, $history->map(fn ($day) => (float) $day->position),
            minStdDev: 0.5, higherIsBetter: false,
            actionsPositive: ['identify_growth_driver'],
            actionsNegative: ['check_technical_issues', 'check_serp_competition', 'check_backlinks'],
        );

        $this->recordSiteWideAnomaly(
            $project, $latestDate, SeoOpportunityType::CtrAnomalyPositive, SeoOpportunityType::CtrAnomalyNegative,
            'نرخ کلیک (CTR)', (float) $latest->ctr, $history->map(fn ($day) => (float) $day->ctr),
            minStdDev: 0.01, higherIsBetter: true,
            actionsPositive: ['identify_growth_driver'],
            actionsNegative: ['review_title', 'review_meta', 'review_serp'],
        );
    }

    /**
     * Shared z-score check + opportunity recording for one site-wide
     * daily metric - used four times by detectAnomalies() so clicks,
     * impressions, position and CTR don't each need a near-duplicate
     * detector. $higherIsBetter flips which direction counts as
     * "positive" - a higher position NUMBER is a worse rank, so a sudden
     * drop in it is the good anomaly, the opposite of every other metric
     * here.
     *
     * @param  Collection<int, float>  $historyValues
     * @param  array<int, string>  $actionsPositive
     * @param  array<int, string>  $actionsNegative
     */
    private function recordSiteWideAnomaly(
        SeoProject $project,
        Carbon $latestDate,
        SeoOpportunityType $positiveType,
        SeoOpportunityType $negativeType,
        string $metricLabel,
        float $latestValue,
        Collection $historyValues,
        float $minStdDev,
        bool $higherIsBetter,
        array $actionsPositive,
        array $actionsNegative,
    ): void {
        $mean = $historyValues->avg();
        $variance = $historyValues->avg(fn (float $value) => ($value - $mean) ** 2);
        $stdDev = sqrt($variance);

        if ($stdDev < $minStdDev) {
            return;
        }

        $zScore = ($latestValue - $mean) / $stdDev;

        if (abs($zScore) < self::ANOMALY_Z_THRESHOLD) {
            return;
        }

        $isPositive = $higherIsBetter ? $zScore > 0 : $zScore < 0;
        $improvementWord = $higherIsBetter ? 'رشد' : 'بهبود';

        $this->recordOrRefreshOpportunity(
            project: $project,
            type: $isPositive ? $positiveType : $negativeType,
            subjectQuery: null,
            subjectPageUrl: null,
            title: $isPositive
                ? sprintf('%s غیرعادی %s در %s', $improvementWord, $metricLabel, $latestDate->toDateString())
                : sprintf('افت غیرعادی %s در %s', $metricLabel, $latestDate->toDateString()),
            evidence: [
                'date' => $latestDate->toDateString(),
                'value' => round($latestValue, 4),
                'expected_range' => [round($mean - 2 * $stdDev, 4), round($mean + 2 * $stdDev, 4)],
                'z_score' => round($zScore, 2),
            ],
            actions: $isPositive ? $actionsPositive : $actionsNegative,
            priority: abs($zScore) >= 3 ? SeoOpportunityPriority::High : SeoOpportunityPriority::Medium,
            confidence: abs($zScore) >= 3 ? SeoConfidenceLevel::High : SeoConfidenceLevel::Medium,
            dedupeDate: $latestDate,
        );
    }

    /**
     * Same z-score logic, per page - a site-wide aggregate can stay flat
     * while one important page quietly craters or spikes because other
     * pages happen to offset it. Scoped to only the project's busiest
     * pages (see ANOMALY_CANDIDATE_POOL_SIZE's own doc comment).
     */
    private function detectPageAnomalies(SeoProject $project): void
    {
        $this->detectSubjectAnomalies(
            $project,
            SeoPageMetric::class,
            'page_url',
            SeoOpportunityType::PageAnomalyPositive,
            SeoOpportunityType::PageAnomalyNegative,
            'کلیک صفحه',
        );
    }

    /**
     * Same z-score logic, per tracked query - catches a single important
     * keyword quietly tanking (or spiking) even when the site-wide and
     * per-page numbers both look unremarkable. Scoped to only the
     * project's busiest queries (see ANOMALY_CANDIDATE_POOL_SIZE's own
     * doc comment).
     */
    private function detectQueryAnomalies(SeoProject $project): void
    {
        $this->detectSubjectAnomalies(
            $project,
            SeoQueryMetric::class,
            'query',
            SeoOpportunityType::QueryAnomalyPositive,
            SeoOpportunityType::QueryAnomalyNegative,
            'کلمهٔ کلیدی',
        );
    }

    /**
     * @param  class-string<SeoPageMetric|SeoQueryMetric>  $model
     */
    private function detectSubjectAnomalies(
        SeoProject $project,
        string $model,
        string $column,
        SeoOpportunityType $positiveType,
        SeoOpportunityType $negativeType,
        string $labelPrefix,
    ): void {
        $referenceDate = $model::where('seo_project_id', $project->id)->max('date');

        if (! $referenceDate) {
            return;
        }

        $referenceDate = Carbon::parse($referenceDate);
        $windowStart = $referenceDate->clone()->subDays(self::ANOMALY_MIN_HISTORY_DAYS);

        $candidates = $model::where('seo_project_id', $project->id)
            ->whereDate('date', '>=', $windowStart)
            ->whereDate('date', '<=', $referenceDate)
            ->selectRaw("{$column} as label, sum(clicks) as total_clicks")
            ->groupBy($column)
            ->orderByDesc('total_clicks')
            ->limit(self::ANOMALY_CANDIDATE_POOL_SIZE)
            ->pluck('label');

        foreach ($candidates as $subject) {
            $days = $model::where('seo_project_id', $project->id)
                ->where($column, $subject)
                ->orderByDesc('date')
                ->limit(self::ANOMALY_MIN_HISTORY_DAYS + 1)
                ->get(['date', 'clicks']);

            if ($days->count() < self::ANOMALY_MIN_HISTORY_DAYS + 1) {
                continue;
            }

            $latest = $days->first();
            $history = $days->slice(1)->map(fn ($day) => (float) $day->clicks);

            $mean = $history->avg();
            $variance = $history->avg(fn (float $value) => ($value - $mean) ** 2);
            $stdDev = sqrt($variance);

            if ($stdDev < 1.0) {
                continue;
            }

            $zScore = ((float) $latest->clicks - $mean) / $stdDev;

            if (abs($zScore) < self::ANOMALY_Z_THRESHOLD) {
                continue;
            }

            $isPositive = $zScore > 0;
            $latestDate = Carbon::parse($latest->date);

            $this->recordOrRefreshOpportunity(
                project: $project,
                type: $isPositive ? $positiveType : $negativeType,
                subjectQuery: $column === 'query' ? $subject : null,
                subjectPageUrl: $column === 'page_url' ? $subject : null,
                title: ($isPositive ? 'رشد غیرعادی ' : 'افت غیرعادی ')."{$labelPrefix}: {$subject}",
                evidence: [
                    'date' => $latestDate->toDateString(),
                    'clicks' => $latest->clicks,
                    'expected_range' => [round($mean - 2 * $stdDev, 1), round($mean + 2 * $stdDev, 1)],
                    'z_score' => round($zScore, 2),
                ],
                actions: $isPositive
                    ? ['identify_growth_driver', 'capitalize_on_trend']
                    : ['check_technical_issues', 'check_indexing', 'review_top_declining_pages'],
                priority: abs($zScore) >= 3 ? SeoOpportunityPriority::High : SeoOpportunityPriority::Medium,
                confidence: abs($zScore) >= 3 ? SeoConfidenceLevel::High : SeoConfidenceLevel::Medium,
            );
        }
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
     * detectCannibalization() above only ever catches two pages ranking
     * for the *exact same* query text - "اجاره ویلا همدان" and "اجاره
     * ویلا در همدان" are two different query_hash values and never
     * compared, even though AiKeywordClassifier already grouped both
     * into the same topic_cluster_id. This is the cluster-level
     * counterpart: several distinct keywords in the same topic, each
     * landing on a different page, is the same underlying problem
     * (several pages competing for one topic) just not caught by an
     * exact-text match - hence the lower, Low confidence, since it's a
     * cluster-level inference rather than a measured click split.
     */
    private function detectClusterCannibalization(SeoProject $project): void
    {
        $byCluster = SeoKeywordProfile::where('seo_project_id', $project->id)
            ->whereNotNull('topic_cluster_id')
            ->whereNotNull('landing_page')
            ->where('impressions_28d', '>=', self::CLUSTER_CANNIBALIZATION_MIN_IMPRESSIONS)
            ->get()
            ->groupBy('topic_cluster_id');

        foreach ($byCluster as $clusterId => $profiles) {
            $distinctPages = $profiles->pluck('landing_page')->unique();

            if ($distinctPages->count() < 2) {
                continue;
            }

            $cluster = SeoTopicCluster::find($clusterId);

            if (! $cluster) {
                continue;
            }

            $pages = $profiles->groupBy('landing_page')->map(fn (Collection $group, string $pageUrl) => [
                'page_url' => $pageUrl,
                'queries' => $group->pluck('query')->all(),
                'clicks' => (int) $group->sum('clicks_28d'),
                'impressions' => (int) $group->sum('impressions_28d'),
            ])->values();

            $this->recordOrRefreshOpportunity(
                project: $project,
                type: SeoOpportunityType::ClusterCannibalization,
                subjectQuery: $cluster->name,
                subjectPageUrl: null,
                title: "چند صفحه برای موضوع «{$cluster->name}» با هم رقابت می‌کنند",
                evidence: ['topic' => $cluster->name, 'pages' => $pages->all()],
                actions: ['choose_primary_page', 'differentiate_or_merge_content', 'update_internal_links_to_primary'],
                priority: $distinctPages->count() >= 3 ? SeoOpportunityPriority::High : SeoOpportunityPriority::Medium,
                confidence: SeoConfidenceLevel::Low,
            );
        }
    }

    /**
     * ContentPerformanceService already knows each published
     * SeoContentItem's real clicks; this is the first place that
     * compares one item's performance against its own topic-cluster
     * peers rather than in isolation - a page doing fine in absolute
     * terms can still be the clear laggard next to every other piece
     * written on the same topic, which is exactly the signal a content
     * refresh decision needs. Needs at least two published items in the
     * same cluster to have a peer average worth comparing against.
     */
    private function detectUnderperformingContent(SeoProject $project): void
    {
        $items = SeoContentItem::where('seo_project_id', $project->id)
            ->where('status', SeoContentItemStatus::Published)
            ->whereNotNull('published_url')
            ->with(['keywordProfile', 'keywordSuggestion'])
            ->get();

        $byCluster = $items
            ->map(fn (SeoContentItem $item) => [
                'item' => $item,
                'cluster_id' => $item->keywordProfile?->topic_cluster_id ?? $item->keywordSuggestion?->topic_cluster_id,
                'clicks' => $this->contentPerformance->summarize($item)['clicks'],
            ])
            ->filter(fn (array $row) => $row['cluster_id'] !== null)
            ->groupBy('cluster_id');

        foreach ($byCluster as $clusterId => $rows) {
            if ($rows->count() < 2) {
                continue;
            }

            $averageClicks = $rows->avg('clicks');

            if ($averageClicks <= 0) {
                continue;
            }

            $cluster = SeoTopicCluster::find($clusterId);

            if (! $cluster) {
                continue;
            }

            foreach ($rows as $row) {
                if ($row['clicks'] >= $averageClicks * self::UNDERPERFORMING_CONTENT_RATIO) {
                    continue;
                }

                $item = $row['item'];

                $this->recordOrRefreshOpportunity(
                    project: $project,
                    type: SeoOpportunityType::UnderperformingContent,
                    subjectQuery: null,
                    subjectPageUrl: $item->published_url,
                    title: "«{$item->title}» خیلی ضعیف‌تر از محتوای مشابه در موضوع «{$cluster->name}» عمل می‌کند",
                    evidence: [
                        'page_url' => $item->published_url,
                        'clicks_28d' => $row['clicks'],
                        'cluster_average_clicks' => round($averageClicks, 1),
                        'topic' => $cluster->name,
                    ],
                    actions: ['improve_content', 'review_internal_links', 'review_search_intent'],
                    priority: SeoOpportunityPriority::Medium,
                    confidence: SeoConfidenceLevel::Low,
                );
            }
        }
    }

    /**
     * Two or more sitemap pages already grouped into the same topic
     * cluster (by OwnSitemapTopicMatcher) are natural candidates to link
     * to each other - search engines read the internal link graph itself
     * as a relevance signal, so a page with zero incoming links from any
     * of its own cluster peers is missing an easy, free win. Reads the
     * most recent *completed* technical audit's crawled link graph, the
     * only source of internal links مرکز سئو has (same "latest completed
     * audit" pattern SeoActionQueueService uses for technical issues) -
     * a project that has never run a technical audit simply has nothing
     * to check yet.
     */
    private function detectMissingInternalLinksByCluster(SeoProject $project): void
    {
        $latestAudit = SeoTechnicalAudit::where('seo_project_id', $project->id)
            ->where('status', SeoTechnicalAuditStatus::Completed)
            ->orderByDesc('completed_at')
            ->first();

        if (! $latestAudit) {
            return;
        }

        $incomingLinksByNormalizedUrl = $latestAudit->internalLinks()
            ->get()
            ->groupBy(fn (SeoInternalLink $link) => SitemapUrlNormalizer::normalize($link->to_url))
            ->map(fn (Collection $links) => $links->map(fn (SeoInternalLink $link) => SitemapUrlNormalizer::normalize($link->from_url))->unique());

        $byCluster = SeoSitemapEntry::where('seo_project_id', $project->id)
            ->whereNotNull('topic_cluster_id')
            ->get()
            ->groupBy('topic_cluster_id');

        foreach ($byCluster as $clusterId => $entries) {
            if ($entries->count() < self::MIN_CLUSTER_SIZE_FOR_LINK_SUGGESTION) {
                continue;
            }

            $cluster = SeoTopicCluster::find($clusterId);

            if (! $cluster) {
                continue;
            }

            $normalizedUrlsInCluster = $entries->map(fn (SeoSitemapEntry $entry) => SitemapUrlNormalizer::normalize($entry->url));

            foreach ($entries as $entry) {
                $normalizedUrl = SitemapUrlNormalizer::normalize($entry->url);
                $incomingFromAnySource = $incomingLinksByNormalizedUrl->get($normalizedUrl, collect());
                $incomingFromClusterPeers = $incomingFromAnySource->intersect(
                    $normalizedUrlsInCluster->reject(fn (string $url) => $url === $normalizedUrl)
                );

                if ($incomingFromClusterPeers->isNotEmpty()) {
                    continue;
                }

                $suggestedLinkingPages = $entries
                    ->reject(fn (SeoSitemapEntry $other) => $other->id === $entry->id)
                    ->pluck('url')
                    ->values()
                    ->all();

                $this->recordOrRefreshOpportunity(
                    project: $project,
                    type: SeoOpportunityType::MissingInternalLinks,
                    subjectQuery: null,
                    subjectPageUrl: $entry->url,
                    title: sprintf('«%s» هیچ لینک داخلی از صفحات هم‌موضوع خود (خوشه‌ی «%s») ندارد', $entry->title ?? $entry->url, $cluster->name),
                    evidence: [
                        'page_url' => $entry->url,
                        'topic' => $cluster->name,
                        'incoming_internal_links' => $incomingFromAnySource->count(),
                        'suggested_linking_pages' => $suggestedLinkingPages,
                    ],
                    actions: ['add_internal_links_from_cluster_pages'],
                    priority: SeoOpportunityPriority::Low,
                    confidence: SeoConfidenceLevel::Low,
                );
            }
        }
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

        $opportunity = SeoOpportunity::create([
            'seo_project_id' => $project->id,
            'type' => $type->value,
            'subject_query' => $subjectQuery,
            'subject_page_url' => $subjectPageUrl,
            'status' => SeoOpportunityStatus::Detected->value,
            ...$attributes,
        ]);

        // Only a genuinely new opportunity pages anyone - refreshing an
        // existing one's numbers above (the $existing branch) never does,
        // or every daily rescan of the same still-open opportunity would
        // send a fresh alert.
        $this->alerts->notifyOpportunityDetected($opportunity);
    }
}
