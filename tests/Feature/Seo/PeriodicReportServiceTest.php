<?php

namespace Tests\Feature\Seo;

use App\Domain\Seo\PeriodicReportService;
use App\Enums\SeoOpportunityOutcomeVerdict;
use App\Models\AiProvider;
use App\Models\SeoContentItem;
use App\Models\SeoDailyMetric;
use App\Models\SeoGoogleConnection;
use App\Models\SeoKeywordRankCheck;
use App\Models\SeoOpportunity;
use App\Models\SeoOpportunityOutcome;
use App\Models\SeoProject;
use App\Models\SeoTrackedKeyword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PeriodicReportServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_summarizes_search_performance_and_stores_the_rendered_html(): void
    {
        $project = SeoProject::factory()->create(['name' => 'پروژهٔ آزمایشی']);
        $start = Carbon::parse('2026-09-01');
        $end = Carbon::parse('2026-09-30');

        SeoDailyMetric::factory()->create(['seo_project_id' => $project->id, 'date' => '2026-09-10', 'clicks' => 100, 'impressions' => 2000, 'position' => 6.0]);
        SeoDailyMetric::factory()->create(['seo_project_id' => $project->id, 'date' => '2026-08-10', 'clicks' => 50, 'impressions' => 1000, 'position' => 8.0]);

        $report = app(PeriodicReportService::class)->generate($project, $start, $end);

        $this->assertSame($project->id, $report->seo_project_id);
        $this->assertSame('2026-09-01', $report->period_start->toDateString());
        $this->assertSame('2026-09-30', $report->period_end->toDateString());
        $this->assertStringContainsString('پروژهٔ آزمایشی', $report->html);
        $this->assertStringContainsString('چاپ / دانلود PDF', $report->html);
    }

    public function test_it_counts_opportunities_and_content_published_within_the_period_only(): void
    {
        $project = SeoProject::factory()->create();
        $start = Carbon::parse('2026-09-01');
        $end = Carbon::parse('2026-09-30');

        SeoOpportunity::factory()->create(['seo_project_id' => $project->id, 'detected_at' => '2026-09-15']);
        SeoOpportunity::factory()->create(['seo_project_id' => $project->id, 'detected_at' => '2026-08-15']);

        $resolvedInPeriod = SeoOpportunity::factory()->create(['seo_project_id' => $project->id, 'detected_at' => '2026-09-05', 'resolved_at' => '2026-09-20']);
        SeoOpportunityOutcome::factory()->create([
            'seo_opportunity_id' => $resolvedInPeriod->id,
            'followup_measured_at' => '2026-09-25',
            'verdict' => SeoOpportunityOutcomeVerdict::Improved,
        ]);

        SeoContentItem::factory()->create(['seo_project_id' => $project->id, 'published_at' => '2026-09-12']);
        SeoContentItem::factory()->create(['seo_project_id' => $project->id, 'published_at' => '2026-08-12']);

        $report = app(PeriodicReportService::class)->generate($project, $start, $end);

        $this->assertStringContainsString('۱', $report->html);
    }

    public function test_it_compares_each_keywords_first_and_last_position_within_the_period(): void
    {
        $project = SeoProject::factory()->create();
        $keyword = SeoTrackedKeyword::factory()->create(['seo_project_id' => $project->id, 'keyword' => 'کلمهٔ نمونه']);

        SeoKeywordRankCheck::factory()->create(['seo_tracked_keyword_id' => $keyword->id, 'date' => '2026-09-01', 'position' => 8]);
        SeoKeywordRankCheck::factory()->create(['seo_tracked_keyword_id' => $keyword->id, 'date' => '2026-09-25', 'position' => 2]);

        $report = app(PeriodicReportService::class)->generate($project, Carbon::parse('2026-09-01'), Carbon::parse('2026-09-30'));

        $this->assertStringContainsString('کلمهٔ نمونه', $report->html);
    }

    public function test_it_includes_no_narrative_section_when_no_ai_provider_is_active(): void
    {
        $project = SeoProject::factory()->create();

        $report = app(PeriodicReportService::class)->generate($project, Carbon::parse('2026-09-01'), Carbon::parse('2026-09-30'));

        $this->assertStringNotContainsString('class="narrative"', $report->html);
    }

    public function test_it_includes_the_ai_narrative_when_a_provider_is_active(): void
    {
        AiProvider::create([
            'name' => 'Test Provider',
            'base_url' => 'https://api.test-provider.example/v1',
            'api_key' => 'test-key',
            'default_model' => 'gpt-test',
            'is_active' => true,
        ]);

        Http::fake([
            'api.test-provider.example/*' => Http::response([
                'choices' => [['message' => ['content' => 'این متن خلاصه‌ی روایی گزارش است.']]],
            ]),
        ]);

        $project = SeoProject::factory()->create();

        $report = app(PeriodicReportService::class)->generate($project, Carbon::parse('2026-09-01'), Carbon::parse('2026-09-30'));

        $this->assertStringContainsString('این متن خلاصه‌ی روایی گزارش است.', $report->html);
    }

    public function test_it_includes_a_daily_clicks_chart_when_there_is_more_than_one_days_data(): void
    {
        $project = SeoProject::factory()->create();
        SeoDailyMetric::factory()->create(['seo_project_id' => $project->id, 'date' => '2026-09-10', 'clicks' => 10]);
        SeoDailyMetric::factory()->create(['seo_project_id' => $project->id, 'date' => '2026-09-11', 'clicks' => 20]);

        $report = app(PeriodicReportService::class)->generate($project, Carbon::parse('2026-09-01'), Carbon::parse('2026-09-30'));

        $this->assertStringContainsString('periodic-report-clicks-chart', $report->html);
    }

    public function test_it_omits_the_chart_when_there_is_at_most_one_days_data(): void
    {
        $project = SeoProject::factory()->create();
        SeoDailyMetric::factory()->create(['seo_project_id' => $project->id, 'date' => '2026-09-10', 'clicks' => 10]);

        $report = app(PeriodicReportService::class)->generate($project, Carbon::parse('2026-09-01'), Carbon::parse('2026-09-30'));

        $this->assertStringNotContainsString('periodic-report-clicks-chart', $report->html);
    }

    public function test_it_refreshes_the_warehouse_from_a_live_search_console_call_before_reading_it(): void
    {
        $project = SeoProject::factory()->create();
        SeoGoogleConnection::factory()->create([
            'seo_project_id' => $project->id,
            'access_token' => 'valid-token',
            'token_expires_at' => now()->addHour(),
            'gsc_site_url' => 'https://ganjnameh.example.com/',
        ]);

        Http::fake([
            '*searchAnalytics/query' => Http::response(['rows' => [
                ['keys' => ['2026-08-30'], 'clicks' => 5, 'impressions' => 100, 'ctr' => 0.05, 'position' => 7.0],
                ['keys' => ['2026-08-31'], 'clicks' => 7, 'impressions' => 120, 'ctr' => 0.05, 'position' => 6.8],
                ['keys' => ['2026-09-01'], 'clicks' => 40, 'impressions' => 1000, 'ctr' => 0.04, 'position' => 6.1],
                ['keys' => ['2026-09-02'], 'clicks' => 10, 'impressions' => 200, 'ctr' => 0.05, 'position' => 6.0],
            ]]),
        ]);

        $report = app(PeriodicReportService::class)->generate($project, Carbon::parse('2026-09-01'), Carbon::parse('2026-09-02'));

        Http::assertSent(fn ($request) => str_contains($request->url(), 'searchAnalytics/query')
            && $request['startDate'] === '2026-08-30'
            && $request['endDate'] === '2026-09-02');

        $this->assertDatabaseHas(SeoDailyMetric::class, [
            'seo_project_id' => $project->id,
            'date' => '2026-09-01',
            'clicks' => 40,
        ]);
        $this->assertStringContainsString('۵۰', $report->html);
    }

    public function test_it_falls_back_to_stored_metrics_when_the_live_refresh_fails(): void
    {
        $project = SeoProject::factory()->create();
        SeoGoogleConnection::factory()->create([
            'seo_project_id' => $project->id,
            'access_token' => 'valid-token',
            'token_expires_at' => now()->addHour(),
            'gsc_site_url' => 'https://ganjnameh.example.com/',
        ]);
        SeoDailyMetric::factory()->create(['seo_project_id' => $project->id, 'date' => '2026-09-10', 'clicks' => 77]);

        Http::fake(['*searchAnalytics/query' => Http::response(['error' => ['message' => 'quota exceeded']], 500)]);

        $report = app(PeriodicReportService::class)->generate($project, Carbon::parse('2026-09-01'), Carbon::parse('2026-09-30'));

        $this->assertStringContainsString('۷۷', $report->html);
    }

    public function test_it_clamps_the_period_to_googles_reporting_lag_and_discloses_it(): void
    {
        $project = SeoProject::factory()->create();

        $report = app(PeriodicReportService::class)->generate($project, now()->subDays(10), now()->addDays(5));

        $this->assertStringContainsString('در دسترس بوده', $report->html);
        $this->assertSame(now()->addDays(5)->toDateString(), $report->period_end->toDateString());
    }
}
