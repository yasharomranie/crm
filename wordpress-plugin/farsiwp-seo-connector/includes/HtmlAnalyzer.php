<?php

namespace FarsiWP\SeoConnector;

/**
 * Reads the structure of a post's HTML - headings, links, images, word count
 * - without any WordPress function, so it is easy to test on its own.
 */
class HtmlAnalyzer
{
    /**
     * @param  string  $html  the post content
     * @param  string  $homeUrl  the site's address, e.g. https://example.com
     * @return array{word_count: int, headings: array, internal_links: array, external_links: array, images: array}
     */
    public static function analyze($html, $homeUrl)
    {
        $result = [
            'word_count' => 0,
            'headings' => [],
            'internal_links' => [],
            'external_links' => [],
            'images' => ['count' => 0, 'missing_alt' => 0],
        ];

        if (trim((string) $html) === '') {
            return $result;
        }

        $previous = libxml_use_internal_errors(true);
        $document = new \DOMDocument;
        $document->loadHTML('<?xml encoding="utf-8" ?><div>'.$html.'</div>', LIBXML_NOERROR | LIBXML_NOWARNING);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $xpath = new \DOMXPath($document);

        foreach ($xpath->query('//script|//style') as $node) {
            $node->parentNode->removeChild($node);
        }

        // Tags become spaces first, so the last word of one paragraph and the
        // first of the next are not glued together.
        $withoutTags = preg_replace('/<[^>]+>/', ' ', (string) $document->saveHTML($document->documentElement));
        $result['word_count'] = self::countWords(html_entity_decode($withoutTags, ENT_QUOTES | ENT_HTML5, 'UTF-8'));

        foreach ($xpath->query('//h1|//h2|//h3|//h4|//h5|//h6') as $heading) {
            $text = self::clean($heading->textContent);

            if ($text !== '') {
                $result['headings'][] = ['level' => (int) substr($heading->nodeName, 1), 'text' => $text];
            }
        }

        $homeHost = self::host($homeUrl);

        foreach ($xpath->query('//a[@href]') as $link) {
            $href = trim($link->getAttribute('href'));

            if ($href === '' || $href[0] === '#' || preg_match('#^(javascript|mailto|tel|sms):#i', $href)) {
                continue;
            }

            $absolute = self::absolute($href, $homeUrl);
            $anchor = self::clean($link->textContent);

            if ($anchor === '') {
                $image = $xpath->query('.//img', $link)->item(0);
                $anchor = $image ? self::clean($image->getAttribute('alt')) : '';
            }

            $entry = [
                'url' => $absolute,
                'anchor' => $anchor,
                'nofollow' => (bool) preg_match('/\b(nofollow|sponsored|ugc)\b/i', $link->getAttribute('rel')),
            ];

            if (self::host($absolute) === $homeHost) {
                $result['internal_links'][] = $entry;
            } else {
                $result['external_links'][] = $entry;
            }
        }

        foreach ($xpath->query('//img') as $image) {
            $result['images']['count']++;

            if (trim($image->getAttribute('alt')) === '') {
                $result['images']['missing_alt']++;
            }
        }

        return $result;
    }

    /**
     * Words are split on whitespace only, so a Persian word joined by a
     * half-space (می‌روم) stays one word.
     */
    public static function countWords($text)
    {
        $words = preg_split('/\s+/u', trim((string) $text), -1, PREG_SPLIT_NO_EMPTY);

        return $words ? count($words) : 0;
    }

    private static function clean($text)
    {
        return trim(preg_replace('/\s+/u', ' ', (string) $text));
    }

    private static function host($url)
    {
        $host = parse_url($url, PHP_URL_HOST);

        return $host ? preg_replace('/^www\./i', '', strtolower($host)) : '';
    }

    private static function absolute($href, $homeUrl)
    {
        if (preg_match('#^https?://#i', $href)) {
            return $href;
        }

        $base = rtrim($homeUrl, '/');

        if (strpos($href, '//') === 0) {
            return (parse_url($base, PHP_URL_SCHEME) ?: 'https').':'.$href;
        }

        return $base.'/'.ltrim($href, '/');
    }
}
