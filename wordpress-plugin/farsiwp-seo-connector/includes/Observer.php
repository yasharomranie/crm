<?php

namespace FarsiWP\SeoConnector;

/**
 * Counts two things the site only knows while visitors are on it: addresses
 * that answered "not found" (404) and what visitors typed into the site's
 * own search box. Only a short, capped tally is kept (no visitor identity,
 * no addresses with query strings, nothing that looks like an e-mail or a
 * phone number), and it is sent only when the owner allowed the matching
 * scope ('health' for 404s, 'search_terms' for searches).
 */
class Observer
{
    const NOT_FOUND = 'fswc_not_found';

    const SEARCHES = 'fswc_searches';

    const MAX_ENTRIES = 300;

    const KEEP_DAYS = 90;

    const MAX_SENT_NOT_FOUND = 50;

    const MAX_SENT_SEARCHES = 100;

    public static function register()
    {
        add_action('template_redirect', [self::class, 'observe'], 99);
    }

    public static function observe()
    {
        if (is_admin() || ! Settings::isConnected()) {
            return;
        }

        if (is_404() && Settings::allows('health')) {
            self::countNotFound();
        } elseif (is_search() && Settings::allows('search_terms')) {
            self::countSearch();
        }
    }

    private static function countNotFound()
    {
        $path = wp_parse_url(isset($_SERVER['REQUEST_URI']) ? wp_unslash($_SERVER['REQUEST_URI']) : '', PHP_URL_PATH);

        if (! is_string($path) || $path === '' || strlen($path) > 200) {
            return;
        }

        $path = rtrim(strtolower(rawurldecode($path)), '/') ?: '/';

        // Scanner noise (probes for admin panels, backups, scripts) says nothing about the site's content.
        if (preg_match('#\.(php|env|sql|zip|bak|git|ini|asp|aspx)$#', $path) || strpos($path, 'wp-') !== false) {
            return;
        }

        self::bump(self::NOT_FOUND, $path, 0);
    }

    private static function countSearch()
    {
        $term = trim(mb_strtolower(wp_strip_all_tags((string) get_search_query(false))));

        if (mb_strlen($term) < 2 || mb_strlen($term) > 80 || strpos($term, '@') !== false || preg_match('/\d{7,}/', $term)) {
            return;
        }

        global $wp_query;

        self::bump(self::SEARCHES, $term, isset($wp_query->found_posts) && (int) $wp_query->found_posts === 0 ? 1 : 0);
    }

    /**
     * @param  string  $option
     * @param  string  $key
     * @param  int  $miss  1 when this visit found nothing (searches)
     */
    private static function bump($option, $key, $miss)
    {
        $tally = get_option($option, []);
        $tally = is_array($tally) ? $tally : [];

        $entry = isset($tally[$key]) ? $tally[$key] : ['n' => 0, 'm' => 0, 't' => 0];
        $tally[$key] = ['n' => $entry['n'] + 1, 'm' => $entry['m'] + $miss, 't' => time()];

        if (count($tally) > self::MAX_ENTRIES) {
            uasort($tally, function ($a, $b) {
                return $b['n'] <=> $a['n'] ?: $b['t'] <=> $a['t'];
            });
            $tally = array_slice($tally, 0, self::MAX_ENTRIES, true);
        }

        update_option($option, $tally, false);
    }

    /**
     * @return array<int, array<string, mixed>> the busiest 404 addresses seen in the last days
     */
    public static function notFound()
    {
        $rows = [];

        foreach (self::recent(self::NOT_FOUND) as $path => $entry) {
            $rows[] = ['path' => (string) $path, 'hits' => (int) $entry['n']];
        }

        usort($rows, function ($a, $b) {
            return $b['hits'] <=> $a['hits'];
        });

        return array_slice($rows, 0, self::MAX_SENT_NOT_FOUND);
    }

    /**
     * @return array<int, array<string, mixed>> the most typed searches seen in the last days
     */
    public static function searches()
    {
        $rows = [];

        foreach (self::recent(self::SEARCHES) as $term => $entry) {
            $rows[] = ['term' => (string) $term, 'count' => (int) $entry['n'], 'misses' => (int) $entry['m']];
        }

        usort($rows, function ($a, $b) {
            return $b['count'] <=> $a['count'];
        });

        return array_slice($rows, 0, self::MAX_SENT_SEARCHES);
    }

    private static function recent($option)
    {
        $tally = get_option($option, []);
        $limit = time() - self::KEEP_DAYS * DAY_IN_SECONDS;

        return is_array($tally)
            ? array_filter($tally, function ($entry) use ($limit) {
                return is_array($entry) && isset($entry['n'], $entry['t']) && $entry['t'] >= $limit;
            })
            : [];
    }

    public static function clear()
    {
        delete_option(self::NOT_FOUND);
        delete_option(self::SEARCHES);
    }
}
