<?php

namespace App\Domain\Seo;

use App\Exceptions\SeoGoogleException;
use Illuminate\Support\Facades\Http;

/**
 * Search Console's "Search Analytics" data still lives under the legacy
 * Webmasters API v3 base URL, not the newer searchconsole.googleapis.com
 * host (which only covers sitemaps/URL inspection/site verification) -
 * see https://developers.google.com/webmaster-tools/v1/how-tos/authorizing.
 * Scope: https://www.googleapis.com/auth/webmasters.readonly.
 */
class SearchConsoleClient
{
    private const BASE_URL = 'https://www.googleapis.com/webmasters/v3';

    /**
     * GSC caps a single query response at 25,000 rows - callers needing
     * more must page with startRow, which this class exposes but does not
     * loop internally (the sync job decides how many pages are worth it).
     */
    public const MAX_ROW_LIMIT = 25000;

    public function __construct(private readonly string $accessToken) {}

    /**
     * @return array<int, array{siteUrl: string, permissionLevel: string}>
     */
    public function listSites(): array
    {
        $response = Http::withToken($this->accessToken)
            ->get(self::BASE_URL.'/sites');

        if ($response->failed()) {
            throw SeoGoogleException::requestFailed($response->json('error.message') ?? "HTTP {$response->status()}");
        }

        return $response->json('siteEntry', []);
    }

    /**
     * @param  array<int, string>  $dimensions  e.g. ['date'], ['query'], ['page'], ['query', 'page'], ['country'], ['device'], ['searchAppearance']
     * @param  array<int, array{dimension: string, operator: string, expression: string}>  $filters  ANDed together (one dimensionFilterGroup) - e.g. [['dimension' => 'query', 'operator' => 'notContains', 'expression' => 'برند']]
     * @param  string  $searchType  web|image|video|news|discover|googleNews - GSC calls this "type" in the request, not a "dimension"
     * @return array<int, array{keys: array<int, string>, clicks: int, impressions: int, ctr: float, position: float}>
     */
    public function querySearchAnalytics(
        string $siteUrl,
        string $startDate,
        string $endDate,
        array $dimensions,
        int $rowLimit = self::MAX_ROW_LIMIT,
        int $startRow = 0,
        array $filters = [],
        string $searchType = 'web',
    ): array {
        $response = Http::withToken($this->accessToken)
            ->post(self::BASE_URL.'/sites/'.rawurlencode($siteUrl).'/searchAnalytics/query', [
                'startDate' => $startDate,
                'endDate' => $endDate,
                'dimensions' => $dimensions,
                'rowLimit' => min($rowLimit, self::MAX_ROW_LIMIT),
                'startRow' => $startRow,
                'dataState' => 'all',
                'type' => $searchType,
                ...(filled($filters) ? ['dimensionFilterGroups' => [['filters' => $filters]]] : []),
            ]);

        if ($response->failed()) {
            throw SeoGoogleException::requestFailed($response->json('error.message') ?? "HTTP {$response->status()}");
        }

        return $response->json('rows', []);
    }
}
