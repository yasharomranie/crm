<?php

namespace Tests\Feature\Seo;

use App\Domain\Seo\GoogleAnalyticsClient;
use App\Domain\Seo\GoogleAnalyticsSyncService;
use App\Domain\Seo\WpConnector\WpPostIssues;
use App\Domain\Seo\WpConnector\WpUrl;
use App\Exceptions\SeoGoogleException;
use App\Jobs\SyncGoogleAnalyticsJob;
use App\Models\SeoGoogleConnection;
use App\Models\SeoProject;
use App\Models\SeoWpPost;
use App\Models\SeoWpSite;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class GoogleAnalyticsSyncTest extends TestCase
{
    use RefreshDatabase;

    private function project(bool $analyticsGranted = true, ?string $property = '4242'): SeoProject
    {
        $project = SeoProject::factory()->create();

        SeoGoogleConnection::factory()->create([
            'seo_project_id' => $project->id,
            'access_token' => 'valid-token',
            'token_expires_at' => now()->addHour(),
            'granted_scopes' => $analyticsGranted
                ? ['https://www.googleapis.com/auth/webmasters.readonly', SeoGoogleConnection::ANALYTICS_SCOPE]
                : ['https://www.googleapis.com/auth/webmasters.readonly'],
            'ga4_property_id' => $property,
        ]);

        return $project->fresh();
    }

    private function wpPost(SeoProject $project, string $url): SeoWpPost
    {
        $site = SeoWpSite::firstOrCreate(['seo_project_id' => $project->id], ['status' => SeoWpSite::STATUS_CONNECTED]);

        return SeoWpPost::factory()->create(['seo_wp_site_id' => $site->id, 'url' => $url, 'url_hash' => WpUrl::key($url)]);
    }

    /**
     * @param  list<array{0: list<string>, 1: list<int|float>}>  $rows
     * @return array<string, mixed>
     */
    private function report(array $rows): array
    {
        return ['rows' => array_map(fn (array $row) => [
            'dimensionValues' => array_map(fn (string $value) => ['value' => $value], $row[0]),
            'metricValues' => array_map(fn ($value) => ['value' => (string) $value], $row[1]),
        ], $rows)];
    }

    private function fakeAnalytics(): void
    {
        Http::fake([
            'analyticsdata.googleapis.com/*' => Http::sequence()
                ->push($this->report([[['/guide/'], [120, 3]], [['/%D8%BA%D8%A7%D8%B1'], [40, 0]], [['/not-on-wordpress'], [9, 1]]]))
                ->push($this->report([[[], [500, 7, 1250.5]]]))
                ->push($this->report([[['chatgpt.com'], [12]], [['perplexity.ai'], [3]]])),
        ]);
    }

    public function test_it_writes_sessions_and_key_events_onto_matching_posts_and_stores_the_summary(): void
    {
        $project = $this->project();
        $guide = $this->wpPost($project, 'https://example.test/guide');
        $cave = $this->wpPost($project, 'https://example.test/غار');
        $quiet = $this->wpPost($project, 'https://example.test/quiet');
        $this->fakeAnalytics();

        app(GoogleAnalyticsSyncService::class)->sync($project);

        $this->assertSame([120, 3], [$guide->fresh()->ga_sessions, $guide->fresh()->ga_key_events]);
        $this->assertSame([40, 0], [$cave->fresh()->ga_sessions, $cave->fresh()->ga_key_events]);
        $this->assertSame([0, 0], [$quiet->fresh()->ga_sessions, $quiet->fresh()->ga_key_events]);

        $connection = $project->googleConnection->fresh();
        $this->assertSame(500, $connection->ga_summary['sessions']);
        $this->assertSame(7, $connection->ga_summary['key_events']);
        $this->assertSame(1250.5, $connection->ga_summary['revenue']);
        $this->assertSame(15, $connection->ga_summary['ai_sessions']);
        $this->assertSame(['chatgpt.com' => 12, 'perplexity.ai' => 3], $connection->ga_summary['ai_sources']);
        $this->assertNotNull($connection->ga_synced_at);
        $this->assertNull($connection->ga_last_error);

        Http::assertSent(fn (Request $request) => str_contains($request->url(), 'properties/4242:runReport')
            && $request->hasHeader('Authorization', 'Bearer valid-token'));
    }

    public function test_it_is_not_ready_without_the_analytics_permission_or_a_property(): void
    {
        $service = app(GoogleAnalyticsSyncService::class);

        $this->assertFalse($service->isReady($this->project(analyticsGranted: false)));
        $this->assertFalse($service->isReady($this->project(property: null)));
        $this->assertTrue($service->isReady($this->project()));

        $this->expectException(SeoGoogleException::class);
        $service->sync($this->project(analyticsGranted: false));
    }

    public function test_the_job_records_a_google_failure_on_the_connection(): void
    {
        $project = $this->project();
        Http::fake(['analyticsdata.googleapis.com/*' => Http::response(['error' => ['message' => 'Google Analytics Data API has not been used in project 1']], 403)]);

        (new SyncGoogleAnalyticsJob($project->id))->handle(app(GoogleAnalyticsSyncService::class));

        $this->assertStringContainsString('Google Analytics Data API has not been used', $project->googleConnection->fresh()->ga_last_error);
        $this->assertNull($project->googleConnection->fresh()->ga_synced_at);
    }

    public function test_the_job_does_nothing_for_a_project_that_is_not_ready(): void
    {
        $project = $this->project(analyticsGranted: false);
        Http::fake();

        (new SyncGoogleAnalyticsJob($project->id))->handle(app(GoogleAnalyticsSyncService::class));

        Http::assertNothingSent();
    }

    public function test_the_command_queues_only_projects_that_are_ready(): void
    {
        Queue::fake();
        $ready = $this->project();
        $this->project(analyticsGranted: false);
        $this->project(property: null);

        $this->assertSame(0, Artisan::call('seo:sync-google-analytics'));

        Queue::assertPushed(SyncGoogleAnalyticsJob::class, 1);
        Queue::assertPushed(SyncGoogleAnalyticsJob::class, fn (SyncGoogleAnalyticsJob $job) => $job->seoProjectId === $ready->id);
    }

    public function test_properties_are_listed_by_id_with_their_account_name(): void
    {
        Http::fake(['analyticsadmin.googleapis.com/*' => Http::response(['accountSummaries' => [
            ['displayName' => 'شرکت', 'propertySummaries' => [['property' => 'properties/777', 'displayName' => 'سایت اصلی']]],
        ]])]);

        $this->assertSame(['777' => 'شرکت / سایت اصلی'], (new GoogleAnalyticsClient('token'))->listProperties());
    }

    public function test_a_busy_page_without_key_events_is_flagged_only_on_a_site_that_tracks_them(): void
    {
        $facts = fn (array $ga) => [
            'status' => 'publish', 'post_type' => 'page', 'url' => 'https://example.test/offer', 'word_count' => 800,
            'seo' => ['description' => 'x', 'focus_keyword' => 'x'], 'images' => null, 'inbound' => 3, 'published_at' => null,
            'gsc' => null, 'has_gsc' => false, 'ga' => $ga,
        ];

        $this->assertContains('no_conversion', WpPostIssues::detect($facts(['sessions' => 150, 'key_events' => 0, 'site_tracks_key_events' => true])));
        $this->assertNotContains('no_conversion', WpPostIssues::detect($facts(['sessions' => 150, 'key_events' => 0, 'site_tracks_key_events' => false])));
        $this->assertNotContains('no_conversion', WpPostIssues::detect($facts(['sessions' => 50, 'key_events' => 0, 'site_tracks_key_events' => true])));
        $this->assertNotContains('no_conversion', WpPostIssues::detect($facts(['sessions' => 150, 'key_events' => 2, 'site_tracks_key_events' => true])));
    }
}
