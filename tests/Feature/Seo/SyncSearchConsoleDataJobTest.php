<?php

namespace Tests\Feature\Seo;

use App\Domain\Seo\GoogleOAuthService;
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
                    ['date', 'searchAppearance'] => ['keys' => ['2026-09-01', 'RICHCARD'], 'clicks' => 3, 'impressions' => 50, 'ctr' => 0.06, 'position' => 3.0],
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
}
