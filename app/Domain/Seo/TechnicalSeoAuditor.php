<?php

namespace App\Domain\Seo;

use App\Enums\SeoTechnicalAuditStatus;
use App\Enums\SeoTechnicalIssueSeverity;
use App\Enums\SeoTechnicalIssueType;
use App\Models\SeoInternalLink;
use App\Models\SeoProject;
use App\Models\SeoSitemapEntry;
use App\Models\SeoTechnicalAudit;
use Illuminate\Http\Client\Response;
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
 * with: broken links, missing/duplicate titles and H1s, missing meta
 * descriptions, a missing canonical/viewport/Open Graph tag, images
 * without alt text or explicit dimensions, slow responses or long
 * redirect chains, thin or duplicate page content, a missing
 * robots.txt/sitemap.xml, phone numbers that disagree across pages, and
 * (on the homepage only) a missing LocalBusiness-family schema - the
 * local-SEO "hygiene" layer: see SeoTechnicalIssueType's Inconsistent
 * PhoneNumber/MissingLocalBusinessSchema cases.
 */
class TechnicalSeoAuditor
{
    /**
     * Raised cautiously from 40, not left unbounded - this directly
     * extends the weekly cron's own run time, and a site with many
     * near-identical listing pages (exactly where duplicate-title/
     * duplicate-content risk is highest) is exactly the case a higher
     * cap matters most for.
     */
    private const MAX_PAGES = 80;

    private const SLOW_PAGE_THRESHOLD_MS = 3000;

    private const REQUEST_TIMEOUT_SECONDS = 10;

    /** 3 or more hops is worth a human looking at, not just "it eventually got there". */
    private const REDIRECT_CHAIN_THRESHOLD = 3;

    /** A hard safety cap against a genuine redirect loop - never meant to be reached on a real chain worth flagging. */
    private const MAX_REDIRECT_HOPS = 10;

    /** Below this many words, a page's own content is too thin to say anything meaningful about it to Google. */
    private const THIN_CONTENT_MIN_WORDS = 100;

    /**
     * schema.org @type values (lowercase) that count as a local-business
     * declaration - LocalBusiness itself plus the lodging/tourism subtypes
     * this app's own customers (villa/resort rental, tourist attractions)
     * actually use. Deliberately homepage-only (see inspectHtml()) - every
     * page on the site (a blog post, say) does not need this.
     *
     * @var array<int, string>
     */
    private const LOCAL_BUSINESS_TYPES = [
        'localbusiness',
        'lodgingbusiness',
        'hotel',
        'resort',
        'bedandbreakfast',
        'campground',
        'touristattraction',
        'touristdestination',
    ];

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
        $normalizedStart = $this->normalize($startUrl);
        $queue = [$startUrl];
        $visited = [];
        $seenTitles = [];
        $seenH1s = [];
        // Keyed by a hash of the page's own visible text, so two pages
        // with genuinely identical content are caught regardless of url -
        // same reasoning as $seenTitles, one level deeper.
        $seenBodyHashes = [];
        // Keyed by "from|to" so the same edge (e.g. a nav link repeated on
        // every page) is only ever recorded once per audit - see
        // App\Models\SeoInternalLink's own doc comment.
        $edges = [];
        // Keyed by normalized phone number => the first URL it was seen
        // on, so more than one distinct number across the whole site can
        // be reported with an example page per number.
        $phoneNumbers = [];
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
                $fetch = $this->fetchFollowingRedirects($url);
            } catch (\Throwable) {
                $this->recordIssue($audit, SeoTechnicalIssueType::BrokenLink, SeoTechnicalIssueSeverity::Critical, $url, 'درخواست ناموفق بود (اتصال برقرار نشد یا timeout).');

                continue;
            }

            $response = $fetch['response'];
            $elapsedMs = (int) round((microtime(true) - $start) * 1000);

            if ($response->failed()) {
                $this->recordIssue($audit, SeoTechnicalIssueType::BrokenLink, SeoTechnicalIssueSeverity::Critical, $url, "کد پاسخ: {$response->status()}");

                continue;
            }

            if ($fetch['hops'] >= self::REDIRECT_CHAIN_THRESHOLD) {
                $this->recordIssue($audit, SeoTechnicalIssueType::RedirectChain, SeoTechnicalIssueSeverity::Warning, $url, "{$fetch['hops']} ریدایرکت پشت‌سرهم تا رسیدن به {$fetch['finalUrl']}");
            }

            if ($elapsedMs > self::SLOW_PAGE_THRESHOLD_MS) {
                $this->recordIssue($audit, SeoTechnicalIssueType::SlowPage, SeoTechnicalIssueSeverity::Warning, $url, "زمان پاسخ: {$elapsedMs} میلی‌ثانیه");
            }

            if (! str_contains((string) $response->header('Content-Type'), 'html')) {
                continue;
            }

            $this->inspectHtml($response->body(), $url, $audit, $seenTitles, $seenH1s, $seenBodyHashes, $normalized === $normalizedStart);

            foreach ($this->extractPhoneNumbers($response->body()) as $phoneNumber) {
                $phoneNumbers[$phoneNumber] ??= $url;
            }

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

        if (count($phoneNumbers) > 1) {
            $this->recordIssue(
                $audit,
                SeoTechnicalIssueType::InconsistentPhoneNumber,
                SeoTechnicalIssueSeverity::Warning,
                $startUrl,
                'شماره‌های تماس متفاوتی در صفحات مختلف سایت پیدا شد: '.implode('، ', array_map(
                    fn (string $number, string $foundOn) => "{$number} ({$foundOn})",
                    array_keys($phoneNumbers),
                    array_values($phoneNumbers),
                )),
            );
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
     * Follows redirects manually (auto-following disabled) rather than
     * letting the HTTP client do it transparently, purely to count the
     * hops - a long chain is itself a real performance/crawl-budget
     * issue even when it eventually lands somewhere valid. Everything
     * else about how a redirecting URL is treated (which response body
     * gets inspected, what's recorded as "the page") is unchanged from
     * before this existed.
     *
     * @return array{response: Response, hops: int, finalUrl: string}
     */
    private function fetchFollowingRedirects(string $url): array
    {
        $currentUrl = $url;
        $hops = 0;

        while (true) {
            $response = Http::timeout(self::REQUEST_TIMEOUT_SECONDS)
                ->withUserAgent('GanjnamehTechnicalSeoAuditor/1.0')
                ->withOptions(['allow_redirects' => false])
                ->get($currentUrl);

            if (! in_array($response->status(), [301, 302, 303, 307, 308], true) || $hops >= self::MAX_REDIRECT_HOPS) {
                return ['response' => $response, 'hops' => $hops, 'finalUrl' => $currentUrl];
            }

            $location = $response->header('Location');
            $resolved = blank($location) ? null : $this->resolveUrl($currentUrl, $location);

            if (! $resolved) {
                return ['response' => $response, 'hops' => $hops, 'finalUrl' => $currentUrl];
            }

            $currentUrl = $resolved;
            $hops++;
        }
    }

    /**
     * @param  array<string, true>  $seenTitles  passed by reference - shared across the whole crawl so a title reused on a second page is flagged as a duplicate
     * @param  array<string, true>  $seenH1s  same idea as $seenTitles, for each page's H1
     * @param  array<string, string>  $seenBodyHashes  hash of a page's own visible text => the first url it was seen on
     */
    private function inspectHtml(string $html, string $url, SeoTechnicalAudit $audit, array &$seenTitles, array &$seenH1s, array &$seenBodyHashes, bool $isHomepage = false): void
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

        $h1Nodes = $document->getElementsByTagName('h1');

        if ($h1Nodes->length === 0) {
            $this->recordIssue($audit, SeoTechnicalIssueType::MissingH1, SeoTechnicalIssueSeverity::Warning, $url, null);
        } elseif ($h1Nodes->length > 1) {
            $this->recordIssue($audit, SeoTechnicalIssueType::MultipleH1, SeoTechnicalIssueSeverity::Warning, $url, "{$h1Nodes->length} سرتیتر (H1) روی یک صفحه.");
        } else {
            $h1 = trim($h1Nodes->item(0)->textContent);

            if ($h1 !== '') {
                if (isset($seenH1s[$h1])) {
                    $this->recordIssue($audit, SeoTechnicalIssueType::DuplicateH1, SeoTechnicalIssueSeverity::Warning, $url, "سرتیتر «{$h1}» قبلاً در صفحهٔ دیگری هم استفاده شده.");
                } else {
                    $seenH1s[$h1] = true;
                }
            }
        }

        $hasCanonical = false;

        foreach ($document->getElementsByTagName('link') as $link) {
            if (strtolower($link->getAttribute('rel')) === 'canonical' && trim($link->getAttribute('href')) !== '') {
                $hasCanonical = true;

                break;
            }
        }

        if (! $hasCanonical) {
            $this->recordIssue($audit, SeoTechnicalIssueType::MissingCanonical, SeoTechnicalIssueSeverity::Info, $url, null);
        }

        $hasViewport = false;
        $hasOgTitle = false;
        $hasOgImage = false;

        foreach ($document->getElementsByTagName('meta') as $meta) {
            $name = strtolower($meta->getAttribute('name'));
            $property = strtolower($meta->getAttribute('property'));

            if ($name === 'viewport' && trim($meta->getAttribute('content')) !== '') {
                $hasViewport = true;
            } elseif ($property === 'og:title' && trim($meta->getAttribute('content')) !== '') {
                $hasOgTitle = true;
            } elseif ($property === 'og:image' && trim($meta->getAttribute('content')) !== '') {
                $hasOgImage = true;
            }
        }

        if (! $hasViewport) {
            $this->recordIssue($audit, SeoTechnicalIssueType::MissingViewport, SeoTechnicalIssueSeverity::Warning, $url, null);
        }

        if (! $hasOgTitle || ! $hasOgImage) {
            $missing = array_filter(['og:title' => ! $hasOgTitle, 'og:image' => ! $hasOgImage]);
            $this->recordIssue($audit, SeoTechnicalIssueType::MissingOpenGraph, SeoTechnicalIssueSeverity::Warning, $url, 'موارد ناقص: '.implode('، ', array_keys($missing)));
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
        $imagesWithoutDimensions = 0;

        foreach ($document->getElementsByTagName('img') as $img) {
            if (trim($img->getAttribute('alt')) === '') {
                $imagesWithoutAlt++;
            }

            if (trim($img->getAttribute('width')) === '' || trim($img->getAttribute('height')) === '') {
                $imagesWithoutDimensions++;
            }
        }

        if ($imagesWithoutAlt > 0) {
            $this->recordIssue($audit, SeoTechnicalIssueType::MissingAlt, SeoTechnicalIssueSeverity::Info, $url, "{$imagesWithoutAlt} تصویر بدون متن جایگزین (alt).");
        }

        if ($imagesWithoutDimensions > 0) {
            // No width/height lets the browser's layout shift once the
            // image actually loads - exactly what PageSpeedInsightsClient's
            // own CLS metric measures, just traced back to its cause here.
            $this->recordIssue($audit, SeoTechnicalIssueType::ImageMissingDimensions, SeoTechnicalIssueSeverity::Info, $url, "{$imagesWithoutDimensions} تصویر بدون ابعاد مشخص (width/height).");
        }

        $hasStructuredData = false;
        $jsonLdBlocks = [];

        foreach ($document->getElementsByTagName('script') as $script) {
            if (strtolower($script->getAttribute('type')) === 'application/ld+json') {
                $hasStructuredData = true;
                $jsonLdBlocks[] = $script->textContent;
            }
        }

        if (! $hasStructuredData) {
            $this->recordIssue($audit, SeoTechnicalIssueType::MissingStructuredData, SeoTechnicalIssueSeverity::Info, $url, null);
        } elseif ($isHomepage) {
            // Only meaningful once there IS structured data - no JSON-LD
            // at all is already covered by MissingStructuredData above,
            // and flagging both would just be noise for the same page.
            $types = collect($jsonLdBlocks)
                ->flatMap(fn (string $block) => $this->extractJsonLdTypes($block))
                ->unique();

            if ($types->intersect(self::LOCAL_BUSINESS_TYPES)->isEmpty()) {
                $this->recordIssue($audit, SeoTechnicalIssueType::MissingLocalBusinessSchema, SeoTechnicalIssueSeverity::Warning, $url, null);
            }
        }

        $bodyText = $this->extractVisibleBodyText($document);
        $wordCount = $bodyText === '' ? 0 : count(preg_split('/\s+/u', $bodyText, -1, PREG_SPLIT_NO_EMPTY));

        if ($wordCount < self::THIN_CONTENT_MIN_WORDS) {
            $this->recordIssue($audit, SeoTechnicalIssueType::ThinContent, SeoTechnicalIssueSeverity::Info, $url, "تعداد کلمات محتوای صفحه: {$wordCount}");
        }

        if ($bodyText !== '') {
            $bodyHash = sha1(mb_strtolower($bodyText));

            if (isset($seenBodyHashes[$bodyHash])) {
                $this->recordIssue($audit, SeoTechnicalIssueType::DuplicateContent, SeoTechnicalIssueSeverity::Warning, $url, "محتوای این صفحه با {$seenBodyHashes[$bodyHash]} یکسان است.");
            } else {
                $seenBodyHashes[$bodyHash] = $url;
            }
        }
    }

    /**
     * The page's own visible text, with <script>/<style> contents
     * excluded (DOMElement::textContent otherwise includes their raw
     * JS/CSS source, which would pollute both the word count and the
     * duplicate-content hash) - computed from a detached clone so this
     * never mutates the document the rest of inspectHtml() still reads.
     */
    private function extractVisibleBodyText(\DOMDocument $document): string
    {
        $body = $document->getElementsByTagName('body')->item(0);

        if (! $body) {
            return '';
        }

        $clone = $body->cloneNode(true);

        foreach (['script', 'style'] as $tagName) {
            $nodes = $clone->getElementsByTagName($tagName);

            for ($i = $nodes->length - 1; $i >= 0; $i--) {
                $node = $nodes->item($i);
                $node->parentNode?->removeChild($node);
            }
        }

        return trim(preg_replace('/\s+/u', ' ', $clone->textContent) ?? '');
    }

    /**
     * @return array<int, string> tel: href numbers on the page, normalized to digits-only (with a leading + kept when present)
     */
    private function extractPhoneNumbers(string $html): array
    {
        $previousState = libxml_use_internal_errors(true);
        $document = new \DOMDocument;
        $document->loadHTML('<?xml encoding="utf-8" ?>'.$html);
        libxml_clear_errors();
        libxml_use_internal_errors($previousState);

        $numbers = [];

        foreach ($document->getElementsByTagName('a') as $anchor) {
            $href = trim($anchor->getAttribute('href'));

            if (! str_starts_with($href, 'tel:')) {
                continue;
            }

            $normalized = $this->normalizePhoneNumber(substr($href, 4));

            if ($normalized !== '') {
                $numbers[] = $normalized;
            }
        }

        return array_values(array_unique($numbers));
    }

    private function normalizePhoneNumber(string $raw): string
    {
        $hasPlus = str_starts_with(trim($raw), '+');
        $digits = preg_replace('/\D+/', '', $raw) ?? '';

        if ($digits === '') {
            return '';
        }

        return ($hasPlus ? '+' : '').$digits;
    }

    /**
     * Decodes a JSON-LD <script> block and returns every lowercase @type
     * it declares, including each node inside an @graph wrapper - a
     * single ld+json block commonly bundles several entities that way.
     *
     * @return array<int, string>
     */
    private function extractJsonLdTypes(string $json): array
    {
        $decoded = json_decode($json, true);

        if (! is_array($decoded)) {
            return [];
        }

        $nodes = isset($decoded['@graph']) && is_array($decoded['@graph'])
            ? array_filter($decoded['@graph'], 'is_array')
            : [$decoded];

        $types = [];

        foreach ($nodes as $node) {
            foreach ((array) ($node['@type'] ?? []) as $type) {
                if (is_string($type)) {
                    $types[] = strtolower($type);
                }
            }
        }

        return $types;
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
