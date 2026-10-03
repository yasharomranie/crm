<?php

namespace App\Domain\Seo;

use App\Enums\SeoConfidenceLevel;
use App\Enums\SeoOpportunityPriority;
use App\Enums\SeoOpportunityStatus;
use App\Enums\SeoOpportunityType;
use App\Models\SeoOpportunity;
use App\Models\SeoPageMetric;
use App\Models\SeoProject;
use App\Models\SeoSitemapEntry;
use Illuminate\Support\Collection;

/**
 * The URL-level counterpart to OpportunityEngine - same evidence-backed,
 * confidence-rated approach, but keyed on subject_page_url instead of
 * subject_query, and reading from the sitemap (SeoSitemapEntry) rather
 * than keyword profiles. Three detectors, per the project roadmap's
 * "فاز ۲": a page that ranks but underperforms its CTR benchmark, a
 * long-published page with no recent traffic, and a page old enough
 * that Google should have shown it in search by now but hasn't.
 *
 * Needs a synced sitemap to find anything - a project with none simply
 * gets no URL-level opportunities, the same way OpportunityEngine needs
 * synced keyword data before it finds anything either.
 */
class UrlOpportunityEngine
{
    /**
     * Same reference curve OpportunityEngine uses for keyword-level CTR
     * (see its own doc comment for why it's a rough static benchmark,
     * not scraped from project history) - applied here to a whole
     * page's trailing-28-day numbers instead of one query's.
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

    /** Same trailing-window convention the rest of مرکز سئو uses for "recent" Search Console numbers. */
    private const GSC_WINDOW_DAYS = 28;

    /** A page needs to have existed at least this long before "no recent traffic" means anything - a brand-new page having none yet is normal, not a finding. */
    private const STALE_PAGE_MIN_AGE_DAYS = 90;

    /** Long enough for Google to have crawled and shown a normal page at least once - not a technical audit (that's TechnicalSeoAuditor's job), just "Search Console has never reported an impression for this". */
    private const INVISIBLE_PAGE_MIN_AGE_DAYS = 21;

    /** Past this age, "still invisible" becomes the stale-page detector's concern instead - likely dead/abandoned content, not a crawling delay. */
    private const INVISIBLE_PAGE_MAX_AGE_DAYS = 90;

    public function __construct(private readonly SeoAlertService $alerts) {}

    public function scan(SeoProject $project): void
    {
        $entries = $project->sitemapEntries()->get();

        if ($entries->isEmpty()) {
            return;
        }

        $metricsByNormalizedUrl = $this->pageMetricsByNormalizedUrl($project);

        $this->detectCtrOpportunities($project, $entries, $metricsByNormalizedUrl);
        $this->detectStalePages($project, $entries, $metricsByNormalizedUrl);
        $this->detectInvisibleNewPages($project, $entries, $metricsByNormalizedUrl);
    }

    /**
     * Keyed on SitemapUrlNormalizer::normalize() rather than the raw
     * page_url_hash column - a sitemap entry's URL and the URL Google
     * reports for the same page don't always agree on scheme, "www.",
     * or a trailing slash, and an exact hash match silently drops the
     * join whenever they don't.
     *
     * @return Collection<string, array{clicks: int, impressions: int, position: float}>
     */
    private function pageMetricsByNormalizedUrl(SeoProject $project): Collection
    {
        return SeoPageMetric::where('seo_project_id', $project->id)
            ->where('date', '>=', now()->subDays(self::GSC_WINDOW_DAYS)->toDateString())
            ->get()
            ->groupBy(fn (SeoPageMetric $row) => SitemapUrlNormalizer::normalize($row->page_url))
            ->map(fn (Collection $rows) => [
                'clicks' => (int) $rows->sum('clicks'),
                'impressions' => (int) $rows->sum('impressions'),
                'position' => round((float) $rows->avg('position'), 1),
            ]);
    }

    /**
     * @param  Collection<int, SeoSitemapEntry>  $entries
     * @param  Collection<string, array{clicks: int, impressions: int, position: float}>  $metricsByNormalizedUrl
     */
    private function detectCtrOpportunities(SeoProject $project, Collection $entries, Collection $metricsByNormalizedUrl): void
    {
        foreach ($entries as $entry) {
            $metrics = $metricsByNormalizedUrl->get(SitemapUrlNormalizer::normalize($entry->url));

            if (! $metrics || $metrics['impressions'] < self::MIN_IMPRESSIONS_FOR_CTR_CHECK) {
                continue;
            }

            $benchmark = $this->ctrBenchmarkFor($metrics['position']);

            if ($benchmark === null) {
                continue;
            }

            $ctr = $metrics['impressions'] > 0 ? $metrics['clicks'] / $metrics['impressions'] : 0.0;

            if ($ctr >= $benchmark) {
                continue;
            }

            $estimatedMonthlyClicksGained = round($metrics['impressions'] * ($benchmark - $ctr), 2);

            $this->recordOrRefreshOpportunity(
                project: $project,
                type: SeoOpportunityType::UrlCtrOpportunity,
                subjectPageUrl: $entry->url,
                title: 'این صفحه رتبه دارد اما CTR پایین‌تر از حد انتظار',
                evidence: [
                    'page_url' => $entry->url,
                    'position' => $metrics['position'],
                    'impressions_28d' => $metrics['impressions'],
                    'clicks_28d' => $metrics['clicks'],
                    'ctr_28d' => round($ctr, 4),
                    'benchmark_ctr_for_position' => $benchmark,
                    'estimated_monthly_clicks_if_fixed' => $estimatedMonthlyClicksGained,
                ],
                actions: ['review_title', 'review_meta', 'review_serp'],
                priority: $metrics['impressions'] >= 1000 ? SeoOpportunityPriority::High : SeoOpportunityPriority::Medium,
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
     * @param  Collection<int, SeoSitemapEntry>  $entries
     * @param  Collection<string, array{clicks: int, impressions: int, position: float}>  $metricsByNormalizedUrl
     */
    private function detectStalePages(SeoProject $project, Collection $entries, Collection $metricsByNormalizedUrl): void
    {
        foreach ($entries as $entry) {
            if ($entry->first_seen_at->diffInDays(now()) < self::STALE_PAGE_MIN_AGE_DAYS) {
                continue;
            }

            $metrics = $metricsByNormalizedUrl->get(SitemapUrlNormalizer::normalize($entry->url));

            if (($metrics['clicks'] ?? 0) > 0) {
                continue;
            }

            $this->recordOrRefreshOpportunity(
                project: $project,
                type: SeoOpportunityType::StalePage,
                subjectPageUrl: $entry->url,
                title: 'این صفحه مدت‌ها منتشر شده ولی در ۲۸ روز اخیر هیچ کلیکی نگرفته',
                evidence: [
                    'page_url' => $entry->url,
                    'published_days_ago' => $entry->first_seen_at->diffInDays(now()),
                    'impressions_28d' => $metrics['impressions'] ?? 0,
                    'clicks_28d' => 0,
                ],
                actions: ['review_content_relevance', 'review_internal_links', 'consider_merging_or_redirecting'],
                priority: SeoOpportunityPriority::Low,
                confidence: SeoConfidenceLevel::Medium,
            );
        }
    }

    /**
     * @param  Collection<int, SeoSitemapEntry>  $entries
     * @param  Collection<string, array{clicks: int, impressions: int, position: float}>  $metricsByNormalizedUrl
     */
    private function detectInvisibleNewPages(SeoProject $project, Collection $entries, Collection $metricsByNormalizedUrl): void
    {
        foreach ($entries as $entry) {
            $ageDays = $entry->first_seen_at->diffInDays(now());

            if ($ageDays < self::INVISIBLE_PAGE_MIN_AGE_DAYS || $ageDays > self::INVISIBLE_PAGE_MAX_AGE_DAYS) {
                continue;
            }

            $metrics = $metricsByNormalizedUrl->get(SitemapUrlNormalizer::normalize($entry->url));

            if (($metrics['impressions'] ?? 0) > 0) {
                continue;
            }

            $this->recordOrRefreshOpportunity(
                project: $project,
                type: SeoOpportunityType::InvisibleNewPage,
                subjectPageUrl: $entry->url,
                title: 'این صفحه از انتشار تاکنون هیچ نمایشی در گوگل نداشته',
                evidence: [
                    'page_url' => $entry->url,
                    'published_days_ago' => $ageDays,
                    'impressions_28d' => 0,
                ],
                actions: ['check_indexing_status', 'check_internal_links', 'check_robots_and_sitemap'],
                priority: SeoOpportunityPriority::Medium,
                confidence: SeoConfidenceLevel::Low,
            );
        }
    }

    /**
     * Mirrors OpportunityEngine::recordOrRefreshOpportunity() for the
     * URL-level case: no subject_query, dedup keyed on subject_page_url
     * alone.
     *
     * @param  array<string, mixed>  $evidence
     * @param  array<int, string>  $actions
     */
    private function recordOrRefreshOpportunity(
        SeoProject $project,
        SeoOpportunityType $type,
        string $subjectPageUrl,
        string $title,
        array $evidence,
        array $actions,
        SeoOpportunityPriority $priority,
        SeoConfidenceLevel $confidence,
    ): void {
        $existing = SeoOpportunity::where('seo_project_id', $project->id)
            ->where('type', $type->value)
            ->where('subject_page_url', $subjectPageUrl)
            ->whereIn('status', [SeoOpportunityStatus::Detected->value, SeoOpportunityStatus::Reviewed->value])
            ->first();

        $attributes = [
            'title' => $title,
            'evidence' => $evidence,
            'recommended_actions' => $actions,
            'priority' => $priority->value,
            'confidence' => $confidence->value,
            'detected_at' => now(),
        ];

        if ($existing) {
            $existing->update($attributes);

            return;
        }

        $opportunity = SeoOpportunity::create([
            'seo_project_id' => $project->id,
            'type' => $type->value,
            'subject_query' => null,
            'subject_page_url' => $subjectPageUrl,
            'status' => SeoOpportunityStatus::Detected->value,
            ...$attributes,
        ]);

        // Only a genuinely new opportunity pages anyone - see
        // OpportunityEngine::recordOrRefreshOpportunity()'s own comment
        // for why refreshing an existing one here never does.
        $this->alerts->notifyOpportunityDetected($opportunity);
    }
}
