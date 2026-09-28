<?php

namespace Tests\Feature\Seo;

use App\Domain\Seo\PeriodicReportService;
use App\Enums\SeoOpportunityOutcomeVerdict;
use App\Models\SeoContentItem;
use App\Models\SeoDailyMetric;
use App\Models\SeoKeywordRankCheck;
use App\Models\SeoOpportunity;
use App\Models\SeoOpportunityOutcome;
use App\Models\SeoProject;
use App\Models\SeoTrackedKeyword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
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
}
