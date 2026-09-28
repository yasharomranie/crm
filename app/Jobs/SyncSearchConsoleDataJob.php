<?php

namespace App\Jobs;

use App\Domain\Seo\GoogleOAuthService;
use App\Domain\Seo\SearchConsoleClient;
use App\Enums\SeoConnectionStatus;
use App\Exceptions\SeoGoogleException;
use App\Models\SeoCountryMetric;
use App\Models\SeoDailyMetric;
use App\Models\SeoDeviceMetric;
use App\Models\SeoGoogleConnection;
use App\Models\SeoPageMetric;
use App\Models\SeoProject;
use App\Models\SeoQueryMetric;
use App\Models\SeoQueryPageMetric;
use App\Models\SeoSearchAppearanceMetric;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Collection;

/**
 * Pulls Search Console performance data for one SEO Project and upserts it
 * into the data-warehouse tables (App\Models\Seo*Metric) - the one place
 * every reporting/intelligence module downstream reads from. Re-running
 * this for an already-synced date range is safe: every table's unique key
 * makes the upsert idempotent, so a retried or manually re-triggered sync
 * never double-counts.
 *
 * Always re-pulls a fixed trailing window (not just "since last sync"):
 * GSC's own data for the most recent ~2-3 days is provisional and keeps
 * changing after it first appears, so a narrower "only what's new"
 * incremental sync would leave those days permanently slightly wrong.
 */
class SyncSearchConsoleDataJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /**
     * @var array<int, int>
     */
    public array $backoff = [30, 120, 600];

    private const SYNC_WINDOW_DAYS = 90;

    /** GSC typically has no data for the most recent 1-3 days yet. */
    private const REPORTING_LAG_DAYS = 3;

    public function __construct(public int $seoProjectId) {}

    public function handle(GoogleOAuthService $oauth): void
    {
        $project = SeoProject::findOrFail($this->seoProjectId);
        $connection = $project->googleConnection;

        if (! $connection || blank($connection->gsc_site_url)) {
            throw SeoGoogleException::notConnected();
        }

        try {
            $accessToken = $this->freshAccessToken($connection, $oauth);
            $client = new SearchConsoleClient($accessToken);

            $endDate = now()->subDays(self::REPORTING_LAG_DAYS);
            $startDate = $endDate->clone()->subDays(self::SYNC_WINDOW_DAYS);

            $this->syncDaily($client, $project, $connection->gsc_site_url, $startDate, $endDate);
            $this->syncQueries($client, $project, $connection->gsc_site_url, $startDate, $endDate);
            $this->syncPages($client, $project, $connection->gsc_site_url, $startDate, $endDate);
            $this->syncQueryPages($client, $project, $connection->gsc_site_url, $startDate, $endDate);
            $this->syncCountries($client, $project, $connection->gsc_site_url, $startDate, $endDate);
            $this->syncDevices($client, $project, $connection->gsc_site_url, $startDate, $endDate);
            $this->syncSearchAppearance($client, $project, $connection->gsc_site_url, $startDate, $endDate);

            $connection->update([
                'status' => SeoConnectionStatus::Connected,
                'last_synced_at' => now(),
                'last_sync_error' => null,
            ]);

            AnalyzeSeoProjectJob::dispatch($project->id);
        } catch (SeoGoogleException $exception) {
            $connection->update([
                'status' => SeoConnectionStatus::Error,
                'last_sync_error' => $exception->getMessage(),
            ]);

            throw $exception;
        }
    }

    private function freshAccessToken(SeoGoogleConnection $connection, GoogleOAuthService $oauth): string
    {
        if (! $connection->isTokenExpired()) {
            return $connection->access_token;
        }

        if (blank($connection->refresh_token)) {
            $connection->update(['status' => SeoConnectionStatus::TokenExpired]);

            throw SeoGoogleException::requestFailed('نشست منقضی شده و Refresh Token موجود نیست - نیاز به اتصال مجدد.');
        }

        $tokens = $oauth->refreshAccessToken($connection->refresh_token);

        $connection->update([
            'access_token' => $tokens['access_token'],
            'token_expires_at' => now()->addSeconds($tokens['expires_in']),
        ]);

        return $tokens['access_token'];
    }

    private function syncDaily(SearchConsoleClient $client, SeoProject $project, string $siteUrl, $startDate, $endDate): void
    {
        $rows = $client->querySearchAnalytics($siteUrl, $startDate->toDateString(), $endDate->toDateString(), ['date']);

        $this->upsert(SeoDailyMetric::class, collect($rows)->map(fn (array $row) => [
            'seo_project_id' => $project->id,
            'date' => $row['keys'][0],
            ...$this->metricValues($row),
        ]), ['seo_project_id', 'date']);
    }

    private function syncQueries(SearchConsoleClient $client, SeoProject $project, string $siteUrl, $startDate, $endDate): void
    {
        $rows = $client->querySearchAnalytics($siteUrl, $startDate->toDateString(), $endDate->toDateString(), ['date', 'query']);

        $this->upsert(SeoQueryMetric::class, collect($rows)->map(fn (array $row) => [
            'seo_project_id' => $project->id,
            'date' => $row['keys'][0],
            'query' => $row['keys'][1],
            'query_hash' => sha1($row['keys'][1]),
            ...$this->metricValues($row),
        ]), ['seo_project_id', 'date', 'query_hash']);
    }

    private function syncPages(SearchConsoleClient $client, SeoProject $project, string $siteUrl, $startDate, $endDate): void
    {
        $rows = $client->querySearchAnalytics($siteUrl, $startDate->toDateString(), $endDate->toDateString(), ['date', 'page']);

        $this->upsert(SeoPageMetric::class, collect($rows)->map(fn (array $row) => [
            'seo_project_id' => $project->id,
            'date' => $row['keys'][0],
            'page_url' => $row['keys'][1],
            'page_url_hash' => sha1($row['keys'][1]),
            ...$this->metricValues($row),
        ]), ['seo_project_id', 'date', 'page_url_hash']);
    }

    private function syncQueryPages(SearchConsoleClient $client, SeoProject $project, string $siteUrl, $startDate, $endDate): void
    {
        $rows = $client->querySearchAnalytics($siteUrl, $startDate->toDateString(), $endDate->toDateString(), ['date', 'query', 'page']);

        $this->upsert(SeoQueryPageMetric::class, collect($rows)->map(fn (array $row) => [
            'seo_project_id' => $project->id,
            'date' => $row['keys'][0],
            'query' => $row['keys'][1],
            'query_hash' => sha1($row['keys'][1]),
            'page_url' => $row['keys'][2],
            'page_url_hash' => sha1($row['keys'][2]),
            ...$this->metricValues($row),
        ]), ['seo_project_id', 'date', 'query_hash', 'page_url_hash']);
    }

    private function syncCountries(SearchConsoleClient $client, SeoProject $project, string $siteUrl, $startDate, $endDate): void
    {
        $rows = $client->querySearchAnalytics($siteUrl, $startDate->toDateString(), $endDate->toDateString(), ['date', 'country']);

        $this->upsert(SeoCountryMetric::class, collect($rows)->map(fn (array $row) => [
            'seo_project_id' => $project->id,
            'date' => $row['keys'][0],
            'country' => $row['keys'][1],
            ...$this->metricValues($row),
        ]), ['seo_project_id', 'date', 'country']);
    }

    private function syncDevices(SearchConsoleClient $client, SeoProject $project, string $siteUrl, $startDate, $endDate): void
    {
        $rows = $client->querySearchAnalytics($siteUrl, $startDate->toDateString(), $endDate->toDateString(), ['date', 'device']);

        $this->upsert(SeoDeviceMetric::class, collect($rows)->map(fn (array $row) => [
            'seo_project_id' => $project->id,
            'date' => $row['keys'][0],
            'device' => $row['keys'][1],
            ...$this->metricValues($row),
        ]), ['seo_project_id', 'date', 'device']);
    }

    /**
     * Unlike every other dimension here, Google's API rejects
     * searchAppearance combined with any other dimension (including date)
     * with "Cannot group by search appearance dimension together with
     * another dimension" - so this queries it alone and gets one row per
     * appearance type aggregated over the whole sync window, stored
     * against the window's end date rather than broken out per day.
     */
    private function syncSearchAppearance(SearchConsoleClient $client, SeoProject $project, string $siteUrl, $startDate, $endDate): void
    {
        $rows = $client->querySearchAnalytics($siteUrl, $startDate->toDateString(), $endDate->toDateString(), ['searchAppearance']);

        $this->upsert(SeoSearchAppearanceMetric::class, collect($rows)->map(fn (array $row) => [
            'seo_project_id' => $project->id,
            'date' => $endDate->toDateString(),
            'search_appearance' => $row['keys'][0],
            ...$this->metricValues($row),
        ]), ['seo_project_id', 'date', 'search_appearance']);
    }

    /**
     * @return array{clicks: int, impressions: int, ctr: float, position: float}
     */
    private function metricValues(array $row): array
    {
        return [
            'clicks' => (int) ($row['clicks'] ?? 0),
            'impressions' => (int) ($row['impressions'] ?? 0),
            'ctr' => (float) ($row['ctr'] ?? 0),
            'position' => (float) ($row['position'] ?? 0),
        ];
    }

    /**
     * @param  class-string<Model>  $model
     * @param  array<int, string>  $uniqueBy
     */
    private function upsert(string $model, Collection $rows, array $uniqueBy): void
    {
        if ($rows->isEmpty()) {
            return;
        }

        $now = now();

        $rows->map(fn (array $row) => [...$row, 'created_at' => $now, 'updated_at' => $now])
            ->chunk(500)
            ->each(fn (Collection $chunk) => $model::query()->upsert(
                $chunk->all(),
                $uniqueBy,
                ['clicks', 'impressions', 'ctr', 'position', 'updated_at'],
            ));
    }
}
