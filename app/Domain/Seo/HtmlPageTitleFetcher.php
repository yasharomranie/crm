<?php

namespace App\Domain\Seo;

use Illuminate\Support\Facades\Http;

/**
 * Fetches one page and reads its <title> - sitemap.xml never carries a
 * title itself, so this is the one place that bridges the gap for
 * whichever sitemap-driven sync needs it. Shared by
 * App\Domain\Seo\SitemapMonitorService (my own project's sitemap) and
 * App\Domain\Seo\CompetitorSitemapCrawler (a competitor's sitemap),
 * rather than each keeping its own copy of the same DOM parsing.
 */
class HtmlPageTitleFetcher
{
    private const REQUEST_TIMEOUT_SECONDS = 8;

    public function fetch(string $url): ?string
    {
        try {
            $response = Http::timeout(self::REQUEST_TIMEOUT_SECONDS)
                ->withUserAgent('GanjnamehSitemapMonitor/1.0')
                ->get($url);

            if ($response->failed() || ! str_contains((string) $response->header('Content-Type'), 'html')) {
                return null;
            }

            $previousState = libxml_use_internal_errors(true);
            $document = new \DOMDocument;
            $document->loadHTML('<?xml encoding="utf-8" ?>'.$response->body());
            libxml_clear_errors();
            libxml_use_internal_errors($previousState);

            $titleNodes = $document->getElementsByTagName('title');
            $title = $titleNodes->length > 0 ? trim($titleNodes->item(0)->textContent) : '';

            return $title !== '' ? $title : null;
        } catch (\Throwable) {
            return null;
        }
    }
}
