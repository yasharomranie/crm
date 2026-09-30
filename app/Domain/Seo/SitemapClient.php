<?php

namespace App\Domain\Seo;

use Illuminate\Support\Facades\Http;

/**
 * Fetches and parses a project's sitemap.xml, resolving both shapes a
 * real sitemap can take: a plain <urlset> of pages, or a <sitemapindex>
 * whose own <sitemap><loc> entries point at the real per-section
 * sitemaps (recursed into, up to MAX_NESTED_SITEMAPS deep) - and
 * transparently gzip-decoding a sitemap.xml.gz. This app's own
 * TechnicalSeoAuditor only ever checks that sitemap.xml *exists*; this
 * is the one place that actually reads what's inside it.
 */
class SitemapClient
{
    private const REQUEST_TIMEOUT_SECONDS = 15;

    /** Guards against an unbounded (or maliciously circular) sitemap-index chain - real sites rarely nest more than one or two levels deep. */
    private const MAX_NESTED_SITEMAPS = 20;

    /**
     * @return array<int, array{loc: string, lastmod: ?string}>
     */
    public function fetch(string $sitemapUrl): array
    {
        return $this->fetchRecursive($sitemapUrl, self::MAX_NESTED_SITEMAPS);
    }

    /**
     * @return array<int, array{loc: string, lastmod: ?string}>
     */
    private function fetchRecursive(string $sitemapUrl, int $remainingDepth): array
    {
        $xml = $this->parseXml($this->fetchBody($sitemapUrl));

        if ($xml === null) {
            throw new \RuntimeException("محتوای sitemap در آدرس {$sitemapUrl} قابل خوانش نبود (XML نامعتبر یا خالی).");
        }

        if ($xml->getName() === 'sitemapindex') {
            return $remainingDepth > 0 ? $this->fetchChildSitemaps($xml, $remainingDepth) : [];
        }

        if ($xml->getName() !== 'urlset') {
            throw new \RuntimeException("ریشه‌ی XML در آدرس {$sitemapUrl} نه sitemapindex است و نه urlset.");
        }

        return $this->extractUrls($xml);
    }

    /**
     * @return array<int, array{loc: string, lastmod: ?string}>
     */
    private function fetchChildSitemaps(\SimpleXMLElement $index, int $remainingDepth): array
    {
        $urls = [];

        foreach ($index->sitemap as $sitemap) {
            $childUrl = trim((string) ($sitemap->loc ?? ''));

            if ($childUrl === '') {
                continue;
            }

            try {
                $urls = [...$urls, ...$this->fetchRecursive($childUrl, $remainingDepth - 1)];
            } catch (\Throwable) {
                // یک sitemap فرزند خراب یا غیرقابل‌دسترس نباید کل sync را متوقف کند.
                continue;
            }
        }

        return $urls;
    }

    /**
     * @return array<int, array{loc: string, lastmod: ?string}>
     */
    private function extractUrls(\SimpleXMLElement $urlset): array
    {
        $entries = [];

        foreach ($urlset->url as $url) {
            $loc = trim((string) ($url->loc ?? ''));

            if ($loc === '') {
                continue;
            }

            $lastmod = trim((string) ($url->lastmod ?? ''));

            $entries[] = ['loc' => $loc, 'lastmod' => $lastmod !== '' ? $lastmod : null];
        }

        return $entries;
    }

    private function fetchBody(string $url): string
    {
        $response = Http::timeout(self::REQUEST_TIMEOUT_SECONDS)
            ->withUserAgent('GanjnamehSitemapMonitor/1.0')
            ->get($url);

        if ($response->failed()) {
            throw new \RuntimeException("دریافت sitemap از {$url} ناموفق بود (کد پاسخ: {$response->status()}).");
        }

        $body = $response->body();

        $looksGzipped = str_ends_with(strtolower((string) parse_url($url, PHP_URL_PATH)), '.gz')
            || str_starts_with($body, "\x1f\x8b");

        if ($looksGzipped) {
            $decoded = @gzdecode($body);

            if ($decoded !== false) {
                return $decoded;
            }
        }

        return $body;
    }

    private function parseXml(string $body): ?\SimpleXMLElement
    {
        $previousState = libxml_use_internal_errors(true);
        $xml = simplexml_load_string($body, \SimpleXMLElement::class, LIBXML_NONET);
        libxml_clear_errors();
        libxml_use_internal_errors($previousState);

        return $xml !== false ? $xml : null;
    }
}
