<?php

namespace App\Domain\Seo;

use App\Enums\SeoTechnicalAuditStatus;
use App\Enums\SeoTechnicalIssueSeverity;
use App\Enums\SeoTechnicalIssueType;
use App\Models\SeoInternalLink;
use App\Models\SeoProject;
use App\Models\SeoSitemapEntry;
use App\Models\SeoTechnicalAudit;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * A self-built crawler for a project's own site - deliberately not a paid
 * third-party audit API (Screaming Frog/Ahrefs/etc. all cost money and
 * this app already has enough "the free option has a catch" trade-offs -
 * see GoogleCustomSearchClient's own doc comment). Crawls up to
 * MAX_PAGES same-host pages breadth-first starting from the project's
 * website_url and flags the checks a real technical SEO review starts
 * with: broken links, missing/duplicate titles, missing meta
 * descriptions, images without alt text, slow responses, and a missing
 * robots.txt/sitemap.xml.
 */
class TechnicalSeoAuditor
{
    private const MAX_PAGES = 40;

    private const SLOW_PAGE_THRESHOLD_MS = 3000;

    private const REQUEST_TIMEOUT_SECONDS = 10;

    public function __construct(private readonly PageSpeedInsightsClient $pageSpeed) {}

    public function audit(SeoProject $project): SeoTechnicalAudit
    {
        $audit = SeoTechnicalAudit::create([
            'seo_project_id' => $project->id,
            'status' => SeoTechnicalAuditStatus::Running,
            'started_at' => now(),
        ]);

        try {
            $crawlResult = $this->crawl($project->website_url, $audit);
            $pagesScanned = $crawlResult['scanned'];
            $this->checkRootFiles($project->website_url, $audit);
            $this->storeInternalLinks($audit, $crawlResult['edges']);
            $this->detectOrphanPages($project, $audit, $crawlResult);

            $audit->update([
                'status' => SeoTechnicalAuditStatus::Completed,
                'pages_scanned' => $pagesScanned,
                'score' => $this->score($audit, $pagesScanned),
                'completed_at' => now(),
                ...$this->runPageSpeedCheck($project),
            ]);
        } catch (\Throwable $exception) {
            Log::warning('TechnicalSeoAuditor: audit failed.', [
                'seo_project_id' => $project->id,
                'exception' => $exception->getMessage(),
            ]);

            $audit->update([
                'status' => SeoTechnicalAuditStatus::Failed,
                'completed_at' => now(),
            ]);
        }

        return $audit->fresh();
    }

    /**
     * Breadth-first, same-host only.
     *
     * @return array{scanned: int, visited: array<string, true>, edges: array<string, array{from: string, to: string}>, exhausted: bool}
     */
    private function crawl(string $startUrl, SeoTechnicalAudit $audit): array
    {
        $host = parse_url($startUrl, PHP_URL_HOST);
        $queue = [$startUrl];
        $visited = [];
        $seenTitles = [];
        // Keyed by "from|to" so the same edge (e.g. a nav link repeated on
        // every page) is only ever recorded once per audit - see
        // App\Models\SeoInternalLink's own doc comment.
        $edges = [];
        $scanned = 0;

        while ($queue !== [] && $scanned < self::MAX_PAGES) {
            $url = array_shift($queue);
            $normalized = $this->normalize($url);

            if (isset($visited[$normalized])) {
                continue;
            }

            $visited[$normalized] = true;
            $scanned++;

            $start = microtime(true);

            try {
                $response = Http::timeout(self::REQUEST_TIMEOUT_SECONDS)
                    ->withUserAgent('GanjnamehTechnicalSeoAuditor/1.0')
                    ->get($url);
            } catch (\Throwable) {
                $this->recordIssue($audit, SeoTechnicalIssueType::BrokenLink, SeoTechnicalIssueSeverity::Critical, $url, 'درخواست ناموفق بود (اتصال برقرار نشد یا timeout).');

                continue;
            }

            $elapsedMs = (int) round((microtime(true) - $start) * 1000);

            if ($response->failed()) {
                $this->recordIssue($audit, SeoTechnicalIssueType::BrokenLink, SeoTechnicalIssueSeverity::Critical, $url, "کد پاسخ: {$response->status()}");

                continue;
            }

            if ($elapsedMs > self::SLOW_PAGE_THRESHOLD_MS) {
                $this->recordIssue($audit, SeoTechnicalIssueType::SlowPage, SeoTechnicalIssueSeverity::Warning, $url, "زمان پاسخ: {$elapsedMs} میلی‌ثانیه");
            }

            if (! str_contains((string) $response->header('Content-Type'), 'html')) {
                continue;
            }

            $this->inspectHtml($response->body(), $url, $audit, $seenTitles);

            foreach ($this->extractSameHostLinks($response->body(), $url, $host) as $link) {
                $normalizedLink = $this->normalize($link);

                // Recorded for every same-host link found, not just ones
                // that get newly enqueued - a link to an already-visited
                // page is still a real edge in the graph, and its target's
                // incoming-link count needs it too.
                $edges["{$normalized}|{$normalizedLink}"] = ['from' => $normalized, 'to' => $normalizedLink];

                // Always enqueued (never gated on the scanned<MAX_PAGES
                // count) - the outer while loop's own condition is what
                // actually stops the crawl, so a page capped out of the
                // run stays in $queue rather than being silently dropped,
                // which is what lets 'exhausted' below tell "the whole
                // site was reached" apart from "the cap cut it short".
                if (! isset($visited[$normalizedLink])) {
                    $queue[] = $link;
                }
            }
        }

        return [
            'scanned' => $scanned,
            'visited' => $visited,
            'edges' => $edges,
            // The queue only ever empties out on its own if every reachable
            // page was actually visited - if MAX_PAGES cut the crawl short
            // instead, $queue still has unvisited URLs in it, which must
            // never be mistaken for "that page doesn't exist on the site".
            'exhausted' => $queue === [],
        ];
    }

    /**
     * @param  array<string, true>  $seenTitles  passed by reference - shared across the whole crawl so a title reused on a second page is flagged as a duplicate
     */
    private function inspectHtml(string $html, string $url, SeoTechnicalAudit $audit, array &$seenTitles): void
    {
        $previousState = libxml_use_internal_errors(true);
        $document = new \DOMDocument;
        $document->loadHTML('<?xml encoding="utf-8" ?>'.$html);
        libxml_clear_errors();
        libxml_use_internal_errors($previousState);

        $titleNodes = $document->getElementsByTagName('title');
        $title = $titleNodes->length > 0 ? trim($titleNodes->item(0)->textContent) : '';

        if ($title === '') {
            $this->recordIssue($audit, SeoTechnicalIssueType::MissingTitle, SeoTechnicalIssueSeverity::Critical, $url, null);
        } elseif (isset($seenTitles[$title])) {
            $this->recordIssue($audit, SeoTechnicalIssueType::DuplicateTitle, SeoTechnicalIssueSeverity::Warning, $url, "عنوان «{$title}» قبلاً در صفحهٔ دیگری هم استفاده شده.");
        } else {
            $seenTitles[$title] = true;
        }

        $hasMetaDescription = false;

        foreach ($document->getElementsByTagName('meta') as $meta) {
            if (strtolower($meta->getAttribute('name')) === 'description' && trim($meta->getAttribute('content')) !== '') {
                $hasMetaDescription = true;

                break;
            }
        }

        if (! $hasMetaDescription) {
            $this->recordIssue($audit, SeoTechnicalIssueType::MissingMetaDescription, SeoTechnicalIssueSeverity::Warning, $url, null);
        }

        $imagesWithoutAlt = 0;

        foreach ($document->getElementsByTagName('img') as $img) {
            if (trim($img->getAttribute('alt')) === '') {
                $imagesWithoutAlt++;
            }
        }

        if ($imagesWithoutAlt > 0) {
            $this->recordIssue($audit, SeoTechnicalIssueType::MissingAlt, SeoTechnicalIssueSeverity::Info, $url, "{$imagesWithoutAlt} تصویر بدون متن جایگزین (alt).");
        }

        $hasStructuredData = false;

        foreach ($document->getElementsByTagName('script') as $script) {
            if (strtolower($script->getAttribute('type')) === 'application/ld+json') {
                $hasStructuredData = true;

                break;
            }
        }

        if (! $hasStructuredData) {
            $this->recordIssue($audit, SeoTechnicalIssueType::MissingStructuredData, SeoTechnicalIssueSeverity::Info, $url, null);
        }
    }

    /**
     * @return array<int, string>
     */
    private function extractSameHostLinks(string $html, string $baseUrl, ?string $host): array
    {
        $previousState = libxml_use_internal_errors(true);
        $document = new \DOMDocument;
        $document->loadHTML('<?xml encoding="utf-8" ?>'.$html);
        libxml_clear_errors();
        libxml_use_internal_errors($previousState);

        $links = [];

        foreach ($document->getElementsByTagName('a') as $anchor) {
            $href = trim($anchor->getAttribute('href'));

            if ($href === '' || str_starts_with($href, '#') || str_starts_with($href, 'mailto:') || str_starts_with($href, 'tel:') || str_starts_with($href, 'javascript:')) {
                continue;
            }

            $resolved = $this->resolveUrl($baseUrl, $href);

            if ($resolved && parse_url($resolved, PHP_URL_HOST) === $host) {
                $links[] = $resolved;
            }
        }

        return $links;
    }

    private function resolveUrl(string $base, string $href): ?string
    {
        if (preg_match('#^https?://#i', $href)) {
            return $href;
        }

        $baseParts = parse_url($base);

        if (! $baseParts || ! isset($baseParts['scheme'], $baseParts['host'])) {
            return null;
        }

        $origin = "{$baseParts['scheme']}://{$baseParts['host']}".(isset($baseParts['port']) ? ":{$baseParts['port']}" : '');

        if (str_starts_with($href, '//')) {
            return "{$baseParts['scheme']}:{$href}";
        }

        if (str_starts_with($href, '/')) {
            return $origin.$href;
        }

        $basePath = $baseParts['path'] ?? '/';
        $baseDir = str_ends_with($basePath, '/') ? $basePath : (dirname($basePath).'/');

        return $origin.$baseDir.$href;
    }

    private function normalize(string $url): string
    {
        return rtrim(strtok($url, '#'), '/');
    }

    private function checkRootFiles(string $websiteUrl, SeoTechnicalAudit $audit): void
    {
        $origin = parse_url($websiteUrl, PHP_URL_SCHEME).'://'.parse_url($websiteUrl, PHP_URL_HOST);

        foreach ([['robots.txt', SeoTechnicalIssueType::MissingRobots], ['sitemap.xml', SeoTechnicalIssueType::MissingSitemap]] as [$path, $issueType]) {
            try {
                $response = Http::timeout(self::REQUEST_TIMEOUT_SECONDS)->get("{$origin}/{$path}");

                if ($response->failed()) {
                    $this->recordIssue($audit, $issueType, SeoTechnicalIssueSeverity::Warning, "{$origin}/{$path}", null);
                }
            } catch (\Throwable) {
                $this->recordIssue($audit, $issueType, SeoTechnicalIssueSeverity::Warning, "{$origin}/{$path}", 'درخواست ناموفق بود.');
            }
        }
    }

    /**
     * Chunked batch insert, not one SeoInternalLink::create() per edge - a
     * crawl can easily discover several hundred deduplicated edges across
     * MAX_PAGES pages, and this only ever needs to run once per audit.
     *
     * @param  array<string, array{from: string, to: string}>  $edges
     */
    private function storeInternalLinks(SeoTechnicalAudit $audit, array $edges): void
    {
        if ($edges === []) {
            return;
        }

        $now = now();

        collect($edges)->values()
            ->map(fn (array $edge) => [
                'seo_technical_audit_id' => $audit->id,
                'from_url' => $edge['from'],
                'to_url' => $edge['to'],
                'created_at' => $now,
                'updated_at' => $now,
            ])
            ->chunk(500)
            ->each(fn (Collection $chunk) => SeoInternalLink::query()->insert($chunk->all()));
    }

    /**
     * Only ever runs when the crawl queue emptied out on its own (see
     * crawl()'s own 'exhausted' flag) - a crawl cut short by MAX_PAGES has
     * only sampled the site, so a sitemap URL it never reached could
     * easily just be "not reached yet", not actually orphaned. Reporting
     * that as a false "orphan page" would be worse than not checking at
     * all - same reasoning as PeriodicReportService never comparing a
     * partial period against a complete one.
     *
     * @param  array{visited: array<string, true>, exhausted: bool}  $crawlResult
     */
    private function detectOrphanPages(SeoProject $project, SeoTechnicalAudit $audit, array $crawlResult): void
    {
        if (! $crawlResult['exhausted']) {
            return;
        }

        $sitemapEntries = SeoSitemapEntry::where('seo_project_id', $project->id)->get();

        if ($sitemapEntries->isEmpty()) {
            return;
        }

        $reachable = collect($crawlResult['visited'])->keys()
            ->map(fn (string $url) => SitemapUrlNormalizer::normalize($url))
            ->flip();

        foreach ($sitemapEntries as $entry) {
            if (! isset($reachable[SitemapUrlNormalizer::normalize($entry->url)])) {
                $this->recordIssue(
                    $audit,
                    SeoTechnicalIssueType::OrphanPage,
                    SeoTechnicalIssueSeverity::Warning,
                    $entry->url,
                    'این صفحه در نقشهٔ سایت هست، ولی در بررسی هیچ لینک داخلی از صفحات دیگر به آن پیدا نشد.',
                );
            }
        }
    }

    private function recordIssue(SeoTechnicalAudit $audit, SeoTechnicalIssueType $type, SeoTechnicalIssueSeverity $severity, ?string $url, ?string $details): void
    {
        $audit->issues()->create([
            'type' => $type,
            'severity' => $severity,
            'url' => $url,
            'details' => $details,
        ]);
    }

    /**
     * 100 minus a deduction based on weighted issues *per page scanned*
     * (not the raw issue count) - critical issues cost more than
     * warnings, which cost more than info-level ones, and scoring by
     * density rather than total count means a handful of duplicate
     * titles doesn't tank a 500-page site's score the way it would a
     * 5-page one, without inverting into the opposite problem of
     * punishing small sites harder than large ones.
     */
    private function score(SeoTechnicalAudit $audit, int $pagesScanned): int
    {
        $weights = [
            SeoTechnicalIssueSeverity::Critical->value => 8,
            SeoTechnicalIssueSeverity::Warning->value => 3,
            SeoTechnicalIssueSeverity::Info->value => 1,
        ];

        $totalWeight = $audit->issues()
            ->get()
            ->sum(fn ($issue) => $weights[$issue->severity->value] ?? 1);

        $densityPerPage = $totalWeight / max(1, $pagesScanned);

        return (int) max(0, min(100, 100 - round($densityPerPage * 5)));
    }

    /**
     * Best-effort, same reasoning as CheckKeywordRankJob catching
     * SeoGoogleException: an unconfigured or failed PageSpeed call must
     * never fail the whole technical audit, which already has useful
     * results (the crawl issues + score) without it - performance_score
     * and core_web_vitals just stay null. The failure itself is still
     * surfaced via performance_error though (shown in the "مشاهده
     * مشکلات" modal) - it used to only go to the log, which left the
     * admin with no way to tell "not configured" apart from "API key
     * rejected" apart from "quota exceeded" from the panel.
     *
     * @return array{performance_score?: ?int, core_web_vitals?: ?array, performance_error?: ?string}
     */
    private function runPageSpeedCheck(SeoProject $project): array
    {
        try {
            $result = $this->pageSpeed->analyze($project->website_url);

            return [
                'performance_score' => $result['performance_score'],
                'core_web_vitals' => [
                    'lcp_ms' => $result['lcp_ms'],
                    'cls' => $result['cls'],
                    'field_data_category' => $result['field_data_category'],
                ],
                'performance_error' => null,
            ];
        } catch (\Throwable $exception) {
            Log::warning('TechnicalSeoAuditor: PageSpeed check failed.', [
                'seo_project_id' => $project->id,
                'exception' => $exception->getMessage(),
            ]);

            return ['performance_error' => $exception->getMessage()];
        }
    }
}
