<?php

namespace App\Domain\Seo;

use App\Enums\SeoTechnicalAuditStatus;
use App\Enums\SeoTechnicalIssueSeverity;
use App\Enums\SeoTechnicalIssueType;
use App\Models\SeoProject;
use App\Models\SeoTechnicalAudit;
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

    public function audit(SeoProject $project): SeoTechnicalAudit
    {
        $audit = SeoTechnicalAudit::create([
            'seo_project_id' => $project->id,
            'status' => SeoTechnicalAuditStatus::Running,
            'started_at' => now(),
        ]);

        try {
            $pagesScanned = $this->crawl($project->website_url, $audit);
            $this->checkRootFiles($project->website_url, $audit);

            $audit->update([
                'status' => SeoTechnicalAuditStatus::Completed,
                'pages_scanned' => $pagesScanned,
                'score' => $this->score($audit, $pagesScanned),
                'completed_at' => now(),
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
     * Breadth-first, same-host only. Returns how many pages were actually
     * fetched (successfully or not - a 404 still counts as "scanned").
     */
    private function crawl(string $startUrl, SeoTechnicalAudit $audit): int
    {
        $host = parse_url($startUrl, PHP_URL_HOST);
        $queue = [$startUrl];
        $visited = [];
        $seenTitles = [];
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

            if ($scanned < self::MAX_PAGES) {
                foreach ($this->extractSameHostLinks($response->body(), $url, $host) as $link) {
                    $normalizedLink = $this->normalize($link);

                    if (! isset($visited[$normalizedLink])) {
                        $queue[] = $link;
                    }
                }
            }
        }

        return $scanned;
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
}
