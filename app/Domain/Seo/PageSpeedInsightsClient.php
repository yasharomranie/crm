<?php

namespace App\Domain\Seo;

use App\Exceptions\SeoGoogleException;
use App\Models\SeoRankTrackerSetting;
use Illuminate\Support\Facades\Http;

/**
 * Plain HTTPS calls to Google's free PageSpeed Insights API (v5) - reuses
 * the same API key already entered for the keyword rank tracker
 * (SeoRankTrackerSetting), since both are ordinary Google Cloud API keys
 * scoped to a project rather than a single API; the admin just needs to
 * also enable "PageSpeed Insights API" on that same Google Cloud project
 * (see SeoRankTrackerSettings' own helper text).
 *
 * Only ever called once per technical audit, against the project's
 * homepage - never per crawled page. PageSpeed Insights runs a real
 * Lighthouse pass server-side and takes several seconds per call, so
 * spending one per page would make a 40-page crawl impractically slow,
 * on top of eating into the free daily quota (25,000/day, shared across
 * whatever else uses the same Google Cloud project).
 */
class PageSpeedInsightsClient
{
    private const ENDPOINT = 'https://www.googleapis.com/pagespeedonline/v5/runPagespeed';

    /**
     * @return array{performance_score: int, lcp_ms: ?float, cls: ?float, field_data_category: ?string}
     */
    public function analyze(string $url): array
    {
        $settings = SeoRankTrackerSetting::current();

        if (! $settings->isConfigured()) {
            throw SeoGoogleException::pageSpeedNotConfigured();
        }

        $response = Http::timeout(30)->get(self::ENDPOINT, [
            'url' => $url,
            'key' => $settings->api_key,
            'strategy' => 'mobile',
            'category' => 'performance',
        ]);

        if ($response->failed()) {
            throw SeoGoogleException::requestFailed($response->json('error.message') ?? "HTTP {$response->status()}");
        }

        $result = $response->json('lighthouseResult', []);
        $audits = $result['audits'] ?? [];
        $performanceScore = $result['categories']['performance']['score'] ?? null;

        return [
            'performance_score' => $performanceScore !== null ? (int) round($performanceScore * 100) : 0,
            'lcp_ms' => $audits['largest-contentful-paint']['numericValue'] ?? null,
            'cls' => $audits['cumulative-layout-shift']['numericValue'] ?? null,
            // Real-user (CrUX) field data, when Google has enough traffic
            // history for this URL to report it - null on a low-traffic or
            // brand-new site, which is normal and not itself a problem.
            'field_data_category' => $response->json('loadingExperience.overall_category'),
        ];
    }
}
