<?php

namespace App\Domain\Seo;

use App\Exceptions\SeoGoogleException;
use App\Models\SeoRankTrackerSetting;
use Illuminate\Support\Facades\Http;

/**
 * Plain HTTPS calls to Google's Custom Search JSON API - the only free,
 * official way to approximate a keyword's organic rank (real Search
 * Console rank tracking only ever exists for keywords that already have
 * impressions - see App\Domain\Seo\RankCheckService for why this is an
 * approximation, not the same index real google.com search results use).
 *
 * Free tier: 100 queries/day, shared across this whole application - not
 * per project. One search() call here = one query against that quota, so
 * this only ever fetches the first page (10 results) to keep each
 * tracked keyword's daily check to a single quota unit.
 */
class GoogleCustomSearchClient
{
    private const BASE_URL = 'https://www.googleapis.com/customsearch/v1';

    private const RESULTS_PER_PAGE = 10;

    /**
     * @return array<int, string> result URLs, in rank order (index 0 = position 1)
     */
    public function search(string $query): array
    {
        $settings = SeoRankTrackerSetting::current();

        if (! $settings->isConfigured()) {
            throw SeoGoogleException::rankTrackerNotConfigured();
        }

        $response = Http::get(self::BASE_URL, [
            'key' => $settings->api_key,
            'cx' => $settings->search_engine_id,
            'q' => $query,
            'num' => self::RESULTS_PER_PAGE,
        ]);

        if ($response->failed()) {
            throw SeoGoogleException::requestFailed($response->json('error.message') ?? "HTTP {$response->status()}");
        }

        return collect($response->json('items', []))->pluck('link')->all();
    }
}
