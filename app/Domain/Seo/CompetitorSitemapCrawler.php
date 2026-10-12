<?php

namespace App\Domain\Seo;

use App\Models\SeoCompetitorSitemapEntry;
use App\Models\SeoProjectCompetitor;
use Illuminate\Support\Carbon;

/**
 * Syncs one competitor's sitemap.xml into App\Models\
 * SeoCompetitorSitemapEntry - the competitor-facing mirror of
 * App\Domain\Seo\SitemapMonitorService, with two differences a
 * competitor's sitemap needs that my own project's sync doesn't:
 * 1. The sitemap URL usually isn't known upfront (App\Domain\Seo\
 *    CompetitorSitemapResolver guesses it).
 * 2. A competitor can have far more pages than I'd ever want to fetch a
 *    title for every sync - capped to the newest MAX_URLS_PER_SYNC by
 *    lastmod, so a large competitor site never multiplies this job's
 *    request count unboundedly.
 */
class CompetitorSitemapCrawler
{
    private const MAX_URLS_PER_SYNC = 300;

    public function __construct(
        private readonly CompetitorSitemapResolver $resolver,
        private readonly SitemapClient $sitemapClient,
        private readonly HtmlPageTitleFetcher $titleFetcher,
    ) {}

    /**
     * @return int the number of brand-new pages found this sync - see
     *             App\Domain\Seo\SeoAlertService::notifyCompetitorActivity(),
     *             which the caller (App\Jobs\AnalyzeCompetitorContentGapsJob)
     *             fires whenever this is greater than zero.
     */
    public function sync(SeoProjectCompetitor $competitor): int
    {
        $sitemapUrl = $this->resolver->resolve($competitor);

        if ($sitemapUrl === null) {
            throw new \RuntimeException("هیچ sitemap قابل‌دسترسی برای رقیب «{$competitor->domain}» پیدا نشد.");
        }

        $entries = collect($this->sitemapClient->fetch($sitemapUrl))
            ->sortByDesc(fn (array $entry) => $entry['lastmod'] ?? '')
            ->take(self::MAX_URLS_PER_SYNC);

        $now = now();
        $newPageCount = 0;

        foreach ($entries as $entry) {
            if ($this->upsertEntry($competitor, $entry['loc'], $entry['lastmod'], $now)) {
                $newPageCount++;
            }
        }

        return $newPageCount;
    }

    private function upsertEntry(SeoProjectCompetitor $competitor, string $url, ?string $lastmodRaw, Carbon $now): bool
    {
        $urlHash = sha1($url);
        $lastmod = $this->parseLastmod($lastmodRaw);

        $existing = SeoCompetitorSitemapEntry::query()
            ->where('seo_project_competitor_id', $competitor->id)
            ->where('url_hash', $urlHash)
            ->first();

        if (! $existing) {
            SeoCompetitorSitemapEntry::create([
                'seo_project_competitor_id' => $competitor->id,
                'url' => $url,
                'url_hash' => $urlHash,
                'lastmod' => $lastmod,
                'title' => $this->titleFetcher->fetch($url),
                'first_seen_at' => $now,
                'last_seen_at' => $now,
                'last_changed_at' => $now,
            ]);

            return true;
        }

        $changed = $lastmod !== null && (! $existing->lastmod || ! $lastmod->equalTo($existing->lastmod));

        $existing->update([
            'lastmod' => $lastmod ?? $existing->lastmod,
            'last_seen_at' => $now,
            'last_changed_at' => $changed ? $now : $existing->last_changed_at,
            // An edited page may now be about something else entirely -
            // its old topic match (or "no match") is no longer trustworthy,
            // so CompetitorTopicMatcher re-reviews it on the next run.
            'classified_at' => $changed ? null : $existing->classified_at,
            'topic_cluster_id' => $changed ? null : $existing->topic_cluster_id,
        ]);

        return false;
    }

    private function parseLastmod(?string $raw): ?Carbon
    {
        if (blank($raw)) {
            return null;
        }

        try {
            return Carbon::parse($raw)->setTimezone(config('app.timezone'));
        } catch (\Throwable) {
            return null;
        }
    }
}
