<?php

namespace App\Domain\Seo;

use App\Models\SeoPageMetric;
use App\Models\SeoProject;
use App\Models\SeoSitemapEntry;
use Illuminate\Support\Collection;

/**
 * Turns the raw rows SitemapMonitorService has already synced into
 * App\Models\SeoSitemapEntry into the actual reporting surface for the
 * project roadmap's "فاز ۱" - health/overview numbers, a growth-over-time
 * series, and a per-page table joined against the Search Console data
 * this app already syncs (SeoPageMetric), so a sitemap entry's clicks/
 * impressions/CTR/position show up next to it without a second query to
 * Google.
 */
class SitemapAnalyzerService
{
    /** Same trailing-window convention KeywordIntelligenceService already uses for "recent" Search Console numbers. */
    private const GSC_WINDOW_DAYS = 28;

    /**
     * @return array{total: int, duplicates: int, missing_lastmod: int, content_types: array<string, int>, lastmod_buckets: array<string, int>}
     */
    public function overview(SeoProject $project): array
    {
        $entries = $project->sitemapEntries()->get();

        return [
            'total' => $entries->count(),
            'duplicates' => $this->countDuplicates($entries),
            'missing_lastmod' => $entries->whereNull('lastmod')->count(),
            'content_types' => $this->contentTypeDistribution($entries),
            'lastmod_buckets' => $this->lastmodBuckets($entries),
        ];
    }

    /**
     * Monthly count of URLs first discovered in that month, oldest first -
     * the raw series a growth chart draws, going back $months from the
     * current month (inclusive).
     *
     * @return array<int, array{label: string, count: int}>
     */
    public function growthSeries(SeoProject $project, int $months = 6): array
    {
        $entries = $project->sitemapEntries()->get();
        $series = [];

        for ($i = $months - 1; $i >= 0; $i--) {
            $monthStart = now()->subMonths($i)->startOfMonth();
            $monthEnd = $monthStart->clone()->endOfMonth();

            $series[] = [
                'label' => $monthStart->translatedFormat('M Y'),
                'count' => $entries->filter(fn (SeoSitemapEntry $entry) => $entry->first_seen_at->between($monthStart, $monthEnd))->count(),
            ];
        }

        return $series;
    }

    /**
     * The per-page table: every sitemap entry, enriched with its trailing
     * 28-day Search Console numbers when there are any (a brand-new page
     * usually has none yet - that gap itself is useful information, so
     * it stays null rather than being coerced to zero).
     *
     * @return Collection<int, array{entry: SeoSitemapEntry, content_type: string, clicks: ?int, impressions: ?int, ctr: ?float, position: ?float}>
     */
    public function pages(SeoProject $project): Collection
    {
        $windowStart = now()->subDays(self::GSC_WINDOW_DAYS)->toDateString();

        $metricsByNormalizedUrl = SeoPageMetric::where('seo_project_id', $project->id)
            ->where('date', '>=', $windowStart)
            ->get()
            ->groupBy(fn (SeoPageMetric $row) => SitemapUrlNormalizer::normalize($row->page_url))
            ->map(fn (Collection $rows) => [
                'clicks' => (int) $rows->sum('clicks'),
                'impressions' => (int) $rows->sum('impressions'),
                'ctr' => $rows->sum('impressions') > 0 ? round($rows->sum('clicks') / $rows->sum('impressions'), 4) : 0.0,
                'position' => round((float) $rows->avg('position'), 1),
            ]);

        return $project->sitemapEntries()->orderByDesc('first_seen_at')->get()
            ->map(function (SeoSitemapEntry $entry) use ($metricsByNormalizedUrl) {
                $metrics = $metricsByNormalizedUrl->get(SitemapUrlNormalizer::normalize($entry->url));

                return [
                    'entry' => $entry,
                    'content_type' => $this->classifyContentType($entry->url),
                    'clicks' => $metrics['clicks'] ?? null,
                    'impressions' => $metrics['impressions'] ?? null,
                    'ctr' => $metrics['ctr'] ?? null,
                    'position' => $metrics['position'] ?? null,
                ];
            });
    }

    /**
     * Two raw URLs that differ only by scheme, a leading "www.", or a
     * trailing slash point at the same page as far as a browser/search
     * engine is concerned - a sitemap listing both is a real sitemap bug
     * worth flagging, not two different pages.
     */
    private function countDuplicates(Collection $entries): int
    {
        return $entries
            ->groupBy(fn (SeoSitemapEntry $entry) => SitemapUrlNormalizer::normalize($entry->url))
            ->filter(fn (Collection $group) => $group->count() > 1)
            ->sum(fn (Collection $group) => $group->count());
    }

    /**
     * @return array<string, int>
     */
    private function contentTypeDistribution(Collection $entries): array
    {
        return $entries
            ->map(fn (SeoSitemapEntry $entry) => $this->classifyContentType($entry->url))
            ->countBy()
            ->sortDesc()
            ->all();
    }

    /**
     * A deliberately simple, honest heuristic from the URL path alone -
     * sitemap.xml carries no content-type field, and this app has no
     * CMS integration to ask instead. Good enough to group a chart by,
     * not precise enough to be treated as ground truth.
     */
    private function classifyContentType(string $url): string
    {
        $path = strtolower((string) parse_url($url, PHP_URL_PATH));

        return match (true) {
            $path === '' || $path === '/' => 'صفحه اصلی',
            (bool) preg_match('#/(product|shop|store)(/|$)#', $path) => 'محصول',
            (bool) preg_match('#/(blog|post|article|news)(/|$)#', $path) => 'پست وبلاگ',
            (bool) preg_match('#/(category|categories)(/|$)#', $path) => 'دسته‌بندی',
            (bool) preg_match('#/tag(s)?(/|$)#', $path) => 'برچسب',
            (bool) preg_match('#/author(/|$)#', $path) => 'نویسنده',
            default => 'صفحه',
        };
    }

    /**
     * @return array<string, int>
     */
    private function lastmodBuckets(Collection $entries): array
    {
        $buckets = [
            '0 تا 7 روز' => 0,
            '8 تا 30 روز' => 0,
            '31 تا 90 روز' => 0,
            '91 تا 180 روز' => 0,
            'بیش از 180 روز' => 0,
            'بدون lastmod' => 0,
        ];

        foreach ($entries as $entry) {
            /** @var SeoSitemapEntry $entry */
            if (! $entry->lastmod) {
                $buckets['بدون lastmod']++;

                continue;
            }

            $age = $entry->lastmod->diffInDays(now());

            $bucket = match (true) {
                $age <= 7 => '0 تا 7 روز',
                $age <= 30 => '8 تا 30 روز',
                $age <= 90 => '31 تا 90 روز',
                $age <= 180 => '91 تا 180 روز',
                default => 'بیش از 180 روز',
            };

            $buckets[$bucket]++;
        }

        return $buckets;
    }
}
