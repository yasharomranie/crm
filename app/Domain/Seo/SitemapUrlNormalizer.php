<?php

namespace App\Domain\Seo;

/**
 * A single, shared way to decide "are these two URLs actually the same
 * page" across مرکز سئو - scheme, a leading "www.", and a trailing
 * slash never change what page a browser or search engine actually
 * loads, but a sitemap.xml entry and Google's own Search Console report
 * don't always agree on which exact form to use for the same page.
 * Matching on the raw string (or a hash of it) can silently fail to
 * join the two even though they're the same page - this is what both
 * SitemapAnalyzerService and UrlOpportunityEngine key their Search
 * Console join on instead.
 */
class SitemapUrlNormalizer
{
    public static function normalize(string $url): string
    {
        $url = preg_replace('#^https?://#i', '', $url);
        $url = preg_replace('#^www\.#i', '', (string) $url);

        return rtrim(strtolower((string) $url), '/');
    }
}
