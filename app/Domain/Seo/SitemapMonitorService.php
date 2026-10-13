<?php

namespace App\Domain\Seo;

use App\Exceptions\SeoGoogleException;
use App\Models\SeoProject;
use App\Models\SeoSitemapEntry;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Syncs one project's sitemap.xml into App\Models\SeoSitemapEntry,
 * keeping enough history (first_seen_at / last_seen_at / last_changed_at)
 * per URL to answer "what's new" and "what changed since last time"
 * across runs - a plain upsert-to-latest-state table (like
 * SeoUrlInspection) couldn't answer either question, since it would
 * have no memory of the previous sync.
 *
 * checkGoogleStatus() is a separate, Google-connection-only concern (how
 * Google itself sees the submitted sitemap, via the free Sitemaps API) -
 * best-effort from App\Jobs\SyncSitemapDataJob, since plenty of projects
 * sync their own sitemap without a Google connection at all.
 *
 * coverageByCluster() reads what App\Domain\Seo\OwnSitemapTopicMatcher
 * classifies those same entries into, after this service's own sync -
 * the two run as separate steps (App\Jobs\SyncSitemapDataJob runs both
 * in sequence) for the same reason App\Domain\Seo\
 * CompetitorSitemapCrawler and App\Domain\Seo\CompetitorTopicMatcher
 * are split: measuring what changed shouldn't be blocked on an AI call,
 * and a sync that only touched a handful of pages shouldn't pay to
 * reclassify the whole sitemap.
 */
class SitemapMonitorService
{
    public function __construct(
        private readonly SitemapClient $client,
        private readonly HtmlPageTitleFetcher $titleFetcher,
        private readonly GoogleOAuthService $oauth,
    ) {}

    public function sync(SeoProject $project): void
    {
        if (blank($project->sitemap_url)) {
            throw new \RuntimeException('آدرس sitemap برای این پروژه ثبت نشده است.');
        }

        $now = now();

        foreach ($this->client->fetch($project->sitemap_url) as $entry) {
            $this->upsertEntry($project, $entry['loc'], $entry['lastmod'], $now);
        }
    }

    /**
     * How many of this project's OWN real sitemap pages (not its content
     * calendar, which an operator could forget to log a page in) already
     * have at least one page classified into each topic cluster - the
     * same shape App\Domain\Seo\CompetitorContentGapService::
     * coverageByCluster() returns for competitors, read by App\Domain\
     * Seo\AiKeywordDiscoveryService and
     * App\Domain\Seo\CompetitorContentGapService::detect() so both can
     * tell "already has real content" apart from "nothing published yet
     * for this topic" using the actual live site, not just what's been
     * manually entered here.
     *
     * @return Collection<int, int> topic_cluster_id => page count
     */
    public function coverageByCluster(SeoProject $project): Collection
    {
        return SeoSitemapEntry::where('seo_project_id', $project->id)
            ->whereNotNull('topic_cluster_id')
            ->get()
            ->groupBy('topic_cluster_id')
            ->map(fn (Collection $entries) => $entries->count());
    }

    /**
     * Google's own free Sitemaps API view of this project's submitted
     * sitemap - errors/warnings Google itself found reading the file,
     * never visible anywhere in this app before (SitemapClient only
     * ever reads the raw XML directly, which can't see what Google made
     * of it). Stored on the project rather than refetched on every page
     * view, since it's a live Google API call.
     *
     * @return array{path?: string, lastSubmitted?: string, isPending?: bool, isSitemapsIndex?: bool, lastDownloaded?: string, warnings?: string, errors?: string}
     */
    public function checkGoogleStatus(SeoProject $project): array
    {
        if (blank($project->sitemap_url)) {
            throw new \RuntimeException('آدرس sitemap برای این پروژه ثبت نشده است.');
        }

        $connection = $project->googleConnection;

        if (! $connection || blank($connection->gsc_site_url)) {
            throw SeoGoogleException::notConnected();
        }

        $accessToken = $this->oauth->freshAccessTokenFor($connection);
        $status = (new SearchConsoleClient($accessToken))->getSitemap($connection->gsc_site_url, $project->sitemap_url);

        $project->update([
            'google_sitemap_status' => $status,
            'google_sitemap_checked_at' => now(),
        ]);

        return $status;
    }

    private function upsertEntry(SeoProject $project, string $url, ?string $lastmodRaw, Carbon $now): void
    {
        $urlHash = sha1($url);
        $lastmod = $this->parseLastmod($lastmodRaw);

        $existing = SeoSitemapEntry::query()
            ->where('seo_project_id', $project->id)
            ->where('url_hash', $urlHash)
            ->first();

        if (! $existing) {
            SeoSitemapEntry::create([
                'seo_project_id' => $project->id,
                'url' => $url,
                'url_hash' => $urlHash,
                'lastmod' => $lastmod,
                'title' => $this->fetchTitle($url),
                'first_seen_at' => $now,
                'last_seen_at' => $now,
                'last_changed_at' => $now,
            ]);

            return;
        }

        // بدون lastmod در sitemap، هیچ راهی برای تشخیص «ویرایش‌شده» وجود
        // ندارد - این صفحه فقط در لیست «تازه اضافه‌شده» ظاهر می‌شود، نه
        // «ویرایش‌شده»، که کاملاً درست و صادقانه است.
        $changed = $lastmod !== null && (! $existing->lastmod || ! $lastmod->equalTo($existing->lastmod));

        $existing->update([
            'lastmod' => $lastmod ?? $existing->lastmod,
            'last_seen_at' => $now,
            'last_changed_at' => $changed ? $now : $existing->last_changed_at,
            // یک صفحهٔ ویرایش‌شده ممکن است موضوعش عوض شده باشد - رده‌بندی
            // قبلی را پاک می‌کنیم تا OwnSitemapTopicMatcher دوباره بررسیش
            // کند، دقیقاً مثل CompetitorSitemapCrawler.
            'classified_at' => $changed ? null : $existing->classified_at,
            'topic_cluster_id' => $changed ? null : $existing->topic_cluster_id,
        ]);
    }

    private function parseLastmod(?string $raw): ?Carbon
    {
        if (blank($raw)) {
            return null;
        }

        try {
            // Normalized to the app's own timezone before it ever reaches
            // Eloquent's datetime cast - saving a Carbon in a different
            // offset keeps its wall-clock digits as-is (Eloquent does not
            // convert to UTC on write), so re-parsing the very same raw
            // sitemap lastmod on the next sync would otherwise land in a
            // different timezone than what got persisted and read back,
            // making an *unchanged* lastmod look changed.
            return Carbon::parse($raw)->setTimezone(config('app.timezone'));
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * sitemap.xml never carries a page title - this is the one place
     * that gap is bridged, and only for brand-new URLs (re-fetching every
     * already-known page's title on every sync would multiply this job's
     * request count by the whole sitemap's size for no real gain).
     */
    private function fetchTitle(string $url): ?string
    {
        return $this->titleFetcher->fetch($url);
    }
}
