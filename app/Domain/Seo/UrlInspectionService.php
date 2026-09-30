<?php

namespace App\Domain\Seo;

use App\Exceptions\SeoGoogleException;
use App\Models\SeoProject;
use App\Models\SeoUrlInspection;
use Carbon\Carbon;

/**
 * Runs a live URL Inspection API call for one page of one project and
 * upserts the result - re-inspecting a URL always refreshes the same
 * row (keyed on seo_project_id + page_url_hash) rather than piling up
 * history, since only "what does Google think right now" matters here.
 */
class UrlInspectionService
{
    public function __construct(private readonly GoogleOAuthService $oauth) {}

    public function inspect(SeoProject $project, string $pageUrl): SeoUrlInspection
    {
        $connection = $project->googleConnection;

        if (! $connection || blank($connection->gsc_site_url)) {
            throw SeoGoogleException::notConnected();
        }

        $accessToken = $this->oauth->freshAccessTokenFor($connection);
        $client = new UrlInspectionClient($accessToken);

        $result = $client->inspect($pageUrl, $connection->gsc_site_url);
        $indexStatus = $result['indexStatusResult'] ?? [];

        return SeoUrlInspection::query()->updateOrCreate(
            [
                'seo_project_id' => $project->id,
                'page_url_hash' => sha1($pageUrl),
            ],
            [
                'page_url' => $pageUrl,
                'verdict' => $indexStatus['verdict'] ?? null,
                'robots_txt_state' => $indexStatus['robotsTxtState'] ?? null,
                'indexing_state' => $indexStatus['indexingState'] ?? null,
                'page_fetch_state' => $indexStatus['pageFetchState'] ?? null,
                'coverage_state' => $indexStatus['coverageState'] ?? null,
                'google_canonical' => $indexStatus['googleCanonical'] ?? null,
                'user_canonical' => $indexStatus['userCanonical'] ?? null,
                'last_crawl_time' => isset($indexStatus['lastCrawlTime']) ? Carbon::parse($indexStatus['lastCrawlTime']) : null,
                'inspected_at' => now(),
                'raw_response' => $result,
            ],
        );
    }
}
