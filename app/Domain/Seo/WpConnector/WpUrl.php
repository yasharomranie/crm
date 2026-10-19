<?php

namespace App\Domain\Seo\WpConnector;

/**
 * One key for "the same page", however it is written: http or https, with
 * or without www, with a trailing slash or a query string, and - because
 * Persian addresses are written both as letters and as %D9%85... - decoded.
 * Used to match a WordPress post with its Search Console rows and with the
 * links that point at it.
 */
class WpUrl
{
    public static function key(string $url): string
    {
        $parts = parse_url(trim($url));
        $host = preg_replace('/^www\./', '', mb_strtolower((string) ($parts['host'] ?? '')));
        $path = rtrim(mb_strtolower(rawurldecode((string) ($parts['path'] ?? ''))), '/');

        return sha1($host.$path);
    }

    public static function isFrontPage(string $url): bool
    {
        return rtrim((string) (parse_url(trim($url))['path'] ?? ''), '/') === '';
    }

    /**
     * Like key() but for the path alone - Google Analytics reports pages as
     * "/path" with no host.
     */
    public static function pathKey(string $urlOrPath): string
    {
        $path = parse_url(trim($urlOrPath), PHP_URL_PATH);
        $path = rtrim(mb_strtolower(rawurldecode((string) $path)), '/');

        return sha1($path);
    }
}
