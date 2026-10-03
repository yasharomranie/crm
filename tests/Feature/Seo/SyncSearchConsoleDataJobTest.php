<?php

namespace Tests\Feature\Seo;

use App\Domain\Seo\GoogleOAuthService;
use App\Domain\Seo\SearchConsoleClient;
use App\Enums\SeoConnectionStatus;
use App\Exceptions\SeoGoogleException;
use App\Jobs\AnalyzeSeoProjectJob;
use App\Jobs\SyncSearchConsoleDataJob;
use App\Models\SeoCountryMetric;
use App\Models\SeoDailyMetric;
use App\Models\SeoDeviceMetric;
use App\Models\SeoGoogleConnection;
use App\Models\SeoGoogleSetting;
use App\Models\SeoPageMetric;
use App\Models\SeoProject;
use App\Models\SeoQueryMetric;
use App\Models\SeoQueryPageMetric;
use App\Models\SeoSearchAppearanceMetric;
use App\Models\SeoSearchTypeMetric;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class SyncSearchConsoleDataJobTest extends TestCase
{
    use RefreshDatabase;

    private function fakeSearchAnalytics(): void
    {
        Http::fake([
            '*searchAnalytics/query' => function ($request) {
                $dimensions = $request['dimensions'];

                $row = match ($dimensions) {
                    ['date'] => ['keys' => ['2026-09-01'], 'clicks' => 40, 'impressions' => 1000, 'ctr' => 0.04, 'position' => 6.1],
                    ['date', 'query'] => ['keys' => ['2026-09-01', 'تله کابین گنجنامه'], 'clicks' => 12, 'impressions' => 300, 'ctr' => 0.04, 'position' => 5.2],
                    ['date', 'page'] => ['keys' => ['2026-09-01', 'https://ganjnameh.example.com/telecabin'], 'clicks' => 20, 'impressions' => 500, 'ctr' => 0.04, 'position' => 4.8],
                    ['date', 'query', 'page'] => ['keys' => ['2026-09-01', 'تله کابین گنجنامه', 'https://ganjnameh.example.com/telecabin'], 'clicks' => 10, 'impressions' => 250, 'ctr' => 0.04, 'position' => 5.0],
                    ['date', 'country'] => ['keys' => ['2026-09-01', 'irn'], 'clicks' => 35, 'impressions' => 900, 'ctr' => 0.04, 'position' => 6.0],
                    ['date', 'device'] => ['keys' => ['2026-09-01', 'MOBILE'], 'clicks' => 25, 'impressions' => 700, 'ctr' => 0.04, 'position' => 6.5],
                    ['searchAppearance'] => ['keys' => ['RICHCARD'], 'clicks' => 3, 'impressions' => 50, 'ctr' => 0.06, 'position' => 3.0],
                    default => null,
                };

                return Http::response(['rows' => $row ? [$row] : []]);
            },
        ]);
    }

    private function connectedProject(): SeoProject
    {
        $project = SeoProject::factory()->create();

        SeoGoogleConnection::factory()->create([
            'seo_project_id' => $project->id,
            'access_token' => 'valid-token',
            'token_expires_at' => now()->addHour(),
            'gsc_site_url' => 'https://ganjnameh.example.com/',
            'status' => SeoConnectionStatus::Connected,
        ]);

        return $project->fresh();
    }

    public function test_it_syncs_every_dimension_table_and_marks_the_connection_synced(): void
    {
        $project = $this->connectedProject();
        $this->fakeSearchAnalytics();

        (new SyncSearchConsoleDataJob($project->id))->handle(app(GoogleOAuthService::class));

        $this->assertDatabaseHas(SeoDailyMetric::class, ['seo_project_id' => $project->id, 'clicks' => 40]);
        $this->assertDatabaseHas(SeoQueryMetric::class, ['seo_project_id' => $project->id, 'query' => 'تله کابین گنجنامه']);
        $this->assertDatabaseHas(SeoPageMetric::class, ['seo_project_id' => $project->id, 'page_url' => 'https://ganjnameh.example.com/telecabin']);
        $this->assertDatabaseHas(SeoQueryPageMetric::class, ['seo_project_id' => $project->id, 'clicks' => 10]);
        $this->assertDatabaseHas(SeoCountryMetric::class, ['seo_project_id' => $project->id, 'country' => 'irn']);
        $this->assertDatabaseHas(SeoDeviceMetric::class, ['seo_project_id' => $project->id, 'device' => 'MOBILE']);
        $this->assertDatabaseHas(SeoSearchAppearanceMetric::class, ['seo_project_id' => $project->id, 'search_appearance' => 'RICHCARD']);

        $project->googleConnection->refresh();
        $this->assertSame(SeoConnectionStatus::Connected, $project->googleConnection->status);
        $this->assertNotNull($project->googleConnection->last_synced_at);
        $this->assertNull($project->googleConnection->last_sync_error);
    }

    public function test_re_running_the_sync_upserts_instead_of_duplicating_rows(): void
    {
        $project = $this->connectedProject();
        $this->fakeSearchAnalytics();

        (new SyncSearchConsoleDataJob($project->id))->handle(app(GoogleOAuthService::class));
        (new SyncSearchConsoleDataJob($project->id))->handle(app(GoogleOAuthService::class));

        $this->assertSame(1, SeoDailyMetric::where('seo_project_id', $project->id)->count());
        $this->assertSame(1, SeoQueryMetric::where('seo_project_id', $project->id)->count());
    }

    public function test_it_refreshes_an_expired_access_token_before_syncing(): void
    {
        $project = SeoProject::factory()->create();
        SeoGoogleConnection::factory()->create([
            'seo_project_id' => $project->id,
            'access_token' => 'expired-token',
            'refresh_token' => 'refresh-123',
            'token_expires_at' => now()->subHour(),
            'gsc_site_url' => 'https://ganjnameh.example.com/',
            'status' => SeoConnectionStatus::Connected,
        ]);

        SeoGoogleSetting::current()->update([
            'client_id' => 'test-client-id',
            'client_secret' => 'test-client-secret',
        ]);

        Http::fake([
            'oauth2.googleapis.com/token' => Http::response(['access_token' => 'fresh-token', 'expires_in' => 3600]),
            '*searchAnalytics/query' => Http::response(['rows' => []]),
        ]);

        (new SyncSearchConsoleDataJob($project->id))->handle(app(GoogleOAuthService::class));

        $this->assertSame('fresh-token', $project->googleConnection->fresh()->access_token);

        Http::assertSent(fn ($request) => str_contains($request->url(), 'searchAnalytics/query')
            && $request->hasHeader('Authorization', 'Bearer fresh-token'));
    }

    public function test_a_successful_sync_chains_an_analysis_job(): void
    {
        Queue::fake();

        $project = $this->connectedProject();
        $this->fakeSearchAnalytics();

        (new SyncSearchConsoleDataJob($project->id))->handle(app(GoogleOAuthService::class));

        Queue::assertPushed(AnalyzeSeoProjectJob::class, fn ($job) => $job->seoProjectId === $project->id);
    }

    public function test_it_excludes_configured_brand_terms_from_the_query_level_sync(): void
    {
        $project = $this->connectedProject();
        $project->update(['excluded_query_terms' => "گنجنامه\nهمدان"]);
        $this->fakeSearchAnalytics();

        (new SyncSearchConsoleDataJob($project->id))->handle(app(GoogleOAuthService::class));

        Http::assertSent(fn ($request) => $request['dimensions'] === ['date', 'query']
            && ($request['dimensionFilterGroups'][0]['filters'] ?? null) === [
                ['dimension' => 'query', 'operator' => 'notContains', 'expression' => 'گنجنامه'],
                ['dimension' => 'query', 'operator' => 'notContains', 'expression' => 'همدان'],
            ]);

        Http::assertSent(fn ($request) => $request['dimensions'] === ['date', 'query', 'page']
            && filled($request['dimensionFilterGroups'] ?? null));

        // Dimensions that never carry a "query" key shouldn't get the filter at all.
        Http::assertSent(fn ($request) => $request['dimensions'] === ['date']
            && ! array_key_exists('dimensionFilterGroups', $request->data()));
    }

    public function test_it_sends_no_filter_group_when_no_terms_are_excluded(): void
    {
        $project = $this->connectedProject();
        $this->fakeSearchAnalytics();

        (new SyncSearchConsoleDataJob($project->id))->handle(app(GoogleOAuthService::class));

        Http::assertSent(fn ($request) => $request['dimensions'] === ['date', 'query']
            && ! array_key_exists('dimensionFilterGroups', $request->data()));
    }

    public function test_it_paginates_the_query_sync_past_the_25000_row_limit(): void
    {
        $project = $this->connectedProject();

        $fullPage = collect(range(1, SearchConsoleClient::MAX_ROW_LIMIT))
            ->map(fn (int $i) => ['keys' => ['2026-09-01', "کلمه شماره {$i}"], 'clicks' => 1, 'impressions' => 1, 'ctr' => 1.0, 'position' => 1.0])
            ->all();

        Http::fake([
            '*searchAnalytics/query' => function ($request) use ($fullPage) {
                if ($request['dimensions'] !== ['date', 'query']) {
                    return Http::response(['rows' => []]);
                }

                return $request['startRow'] === 0
                    ? Http::response(['rows' => $fullPage])
                    : Http::response(['rows' => [['keys' => ['2026-09-01', 'آخرین کلمه'], 'clicks' => 2, 'impressions' => 2, 'ctr' => 1.0, 'position' => 1.0]]]);
            },
        ]);

        (new SyncSearchConsoleDataJob($project->id))->handle(app(GoogleOAuthService::class));

        $this->assertSame(SearchConsoleClient::MAX_ROW_LIMIT + 1, SeoQueryMetric::where('seo_project_id', $project->id)->count());
        $this->assertDatabaseHas(SeoQueryMetric::class, ['seo_project_id' => $project->id, 'query' => 'آخرین کلمه']);

        Http::assertSent(fn ($request) => $request['dimensions'] === ['date', 'query'] && $request['startRow'] === SearchConsoleClient::MAX_ROW_LIMIT);
    }

    public function test_the_sync_window_covers_a_full_year(): void
    {
        $project = $this->connectedProject();
        $this->fakeSearchAnalytics();

        $expectedEndDate = now()->subDays(3)->toDateString();
        $expectedStartDate = now()->subDays(3)->subDays(365)->toDateString();

        (new SyncSearchConsoleDataJob($project->id))->handle(app(GoogleOAuthService::class));

        Http::assertSent(fn ($request) => $request['startDate'] === $expectedStartDate && $request['endDate'] === $expectedEndDate);
    }

    public function test_it_syncs_each_search_type_into_its_own_row(): void
    {
        $project = $this->connectedProject();

        Http::fake([
            '*searchAnalytics/query' => function ($request) {
                if ($request['dimensions'] !== ['date']) {
                    return Http::response(['rows' => []]);
                }

                $clicksByType = ['web' => 40, 'image' => 5, 'video' => 2, 'news' => 1, 'discover' => 8];
                $clicks = $clicksByType[$request['type']] ?? 0;

                return Http::response(['rows' => [
                    ['keys' => ['2026-09-01'], 'clicks' => $clicks, 'impressions' => $clicks * 10, 'ctr' => 0.1, 'position' => 5.0],
                ]]);
            },
        ]);

        (new SyncSearchConsoleDataJob($project->id))->handle(app(GoogleOAuthService::class));

        $this->assertSame(4, SeoSearchTypeMetric::where('seo_project_id', $project->id)->count());
        $this->assertDatabaseHas(SeoSearchTypeMetric::class, ['seo_project_id' => $project->id, 'search_type' => 'image', 'clicks' => 5]);
        $this->assertDatabaseHas(SeoSearchTypeMetric::class, ['seo_project_id' => $project->id, 'search_type' => 'discover', 'clicks' => 8]);
        // "web" is never stored here - it's SeoDailyMetric's whole table.
        $this->assertDatabaseMissing(SeoSearchTypeMetric::class, ['seo_project_id' => $project->id, 'search_type' => 'web']);
    }

    public function test_it_records_the_failure_and_rethrows_when_the_project_has_no_selected_site(): void
    {
        $project = SeoProject::factory()->create();
        SeoGoogleConnection::factory()->create([
            'seo_project_id' => $project->id,
            'gsc_site_url' => null,
            'status' => SeoConnectionStatus::Connected,
        ]);

        $this->expectException(SeoGoogleException::class);

        (new SyncSearchConsoleDataJob($project->id))->handle(app(GoogleOAuthService::class));
    }

    /**
     * Regression: this job can easily make ~20 Search Console API calls
     * in one run (daily, up to 4 pages each of queries/pages/query-pages,
     * countries, devices, search appearance, one per SeoSearchType) -
     * comfortably over the queue worker's default 60-second timeout,
     * especially from a slower-latency host. Without an explicit,
     * longer timeout the job gets killed mid-run before its own
     * exception handling ever runs, so it silently retries and
     * eventually fails with no visible error anywhere.
     */
    public function test_the_job_has_a_timeout_generous_enough_for_its_own_api_call_volume(): void
    {
        $this->assertGreaterThanOrEqual(300, (new SyncSearchConsoleDataJob(1))->timeout);
    }
}
