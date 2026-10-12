<?php

namespace App\Domain\Seo;

use App\Models\SeoProjectCompetitor;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * Figures out a competitor's sitemap URL when the admin didn't set one
 * explicitly (App\Models\SeoProjectCompetitor::sitemap_url) - most SEO
 * platforms (WordPress/Rank Math/Yoast, Shopify, ...) don't all agree on
 * one default path, so this tries the handful of common ones, then falls
 * back to reading the `Sitemap:` directive from robots.txt, exactly the
 * way Google's own crawler discovers a site's sitemap when none was
 * submitted to it directly.
 */
class CompetitorSitemapResolver
{
    private const REQUEST_TIMEOUT_SECONDS = 10;

    /** @var array<int, string> */
    private const COMMON_PATHS = ['/sitemap.xml', '/sitemap_index.xml', '/sitemap-index.xml'];

    public function resolve(SeoProjectCompetitor $competitor): ?string
    {
        if (filled($competitor->sitemap_url)) {
            return $competitor->sitemap_url;
        }

        $base = $this->normalizeDomain($competitor->domain);

        if ($base === null) {
            return null;
        }

        foreach (self::COMMON_PATHS as $path) {
            $candidate = $base.$path;

            if ($this->looksLikeSitemap($candidate)) {
                return $candidate;
            }
        }

        return $this->fromRobotsTxt($base);
    }

    private function normalizeDomain(string $domain): ?string
    {
        $domain = trim($domain);

        if ($domain === '') {
            return null;
        }

        if (! preg_match('#^https?://#i', $domain)) {
            $domain = 'https://'.$domain;
        }

        return rtrim($domain, '/');
    }

    private function looksLikeSitemap(string $url): bool
    {
        try {
            $response = $this->request($url);

            return $response->successful()
                && (str_contains((string) $response->header('Content-Type'), 'xml')
                    || str_starts_with(trim($response->body()), '<?xml'));
        } catch (\Throwable) {
            return false;
        }
    }

    private function fromRobotsTxt(string $base): ?string
    {
        try {
            $response = $this->request($base.'/robots.txt');

            if ($response->failed()) {
                return null;
            }

            foreach (preg_split('/\r\n|\r|\n/', $response->body()) as $line) {
                if (preg_match('/^sitemap:\s*(\S+)/i', trim($line), $matches)) {
                    return trim($matches[1]);
                }
            }

            return null;
        } catch (\Throwable) {
            return null;
        }
    }

    private function request(string $url): Response
    {
        return Http::timeout(self::REQUEST_TIMEOUT_SECONDS)
            ->withUserAgent('GanjnamehCompetitorAudit/1.0')
            ->get($url);
    }
}
