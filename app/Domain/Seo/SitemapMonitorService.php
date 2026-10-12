<?php

namespace App\Domain\Seo;

use App\Models\SeoProject;
use App\Models\SeoSitemapEntry;
use Carbon\Carbon;

/**
 * Syncs one project's sitemap.xml into App\Models\SeoSitemapEntry,
 * keeping enough history (first_seen_at / last_seen_at / last_changed_at)
 * per URL to answer "what's new" and "what changed since last time"
 * across runs - a plain upsert-to-latest-state table (like
 * SeoUrlInspection) couldn't answer either question, since it would
 * have no memory of the previous sync.
 */
class SitemapMonitorService
{
    public function __construct(
        private readonly SitemapClient $client,
        private readonly HtmlPageTitleFetcher $titleFetcher,
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
