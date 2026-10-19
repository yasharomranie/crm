<?php

namespace App\Domain\Seo;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * What people actually type after a few words: the suggestions under
 * Google's search box, free and without a key. It is an unofficial
 * endpoint, so every answer is cached for days and a failure (a blocked
 * host, a changed format) quietly returns nothing.
 */
class GoogleAutocompleteClient
{
    private const ENDPOINT = 'https://suggestqueries.google.com/complete/search';

    /** Words put before the seed so the box answers the way real questions and comparisons begin. */
    private const PREFIXES = ['', 'چگونه ', 'آیا ', 'چرا ', 'بهترین '];

    private const LIMIT = 10;

    /**
     * @return list<string> suggestions for the seed, questions and variations included, without the seed itself
     */
    public function suggestionsFor(string $seed): array
    {
        $seed = trim($seed);

        if ($seed === '' || ! config('seo.autocomplete.enabled')) {
            return [];
        }

        $all = [];

        foreach (self::PREFIXES as $prefix) {
            foreach ($this->fetch($prefix.$seed) as $suggestion) {
                $all[$suggestion] = true;
            }
        }

        return array_values(array_slice(
            array_filter(array_keys($all), fn (string $suggestion) => $suggestion !== $seed),
            0,
            self::LIMIT,
        ));
    }

    /**
     * A failed or empty answer is remembered for an hour only, so a blip
     * does not silence suggestions for a week.
     *
     * @return list<string>
     */
    private function fetch(string $query): array
    {
        $key = 'seo.autocomplete.'.sha1($query);
        $cached = Cache::get($key);

        if (is_array($cached)) {
            return $cached;
        }

        try {
            $response = Http::timeout(5)->get(self::ENDPOINT, ['client' => 'firefox', 'hl' => 'fa', 'q' => $query]);
        } catch (Throwable) {
            $response = null;
        }

        $body = $response?->successful() ? json_decode($response->body(), true) : null;
        $suggestions = is_array($body) && is_array($body[1] ?? null) ? array_values(array_filter($body[1], 'is_string')) : [];

        Cache::put($key, $suggestions, $suggestions === [] ? now()->addHour() : now()->addDays((int) config('seo.autocomplete.cache_days', 7)));

        return $suggestions;
    }
}
