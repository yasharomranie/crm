<?php

namespace Tests\Feature\Seo;

use App\Domain\Seo\OpportunityOutcomeService;
use App\Enums\SeoOpportunityOutcomeVerdict;
use App\Models\SeoDailyMetric;
use App\Models\SeoOpportunity;
use App\Models\SeoProject;
use App\Models\SeoQueryMetric;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class OpportunityOutcomeServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function fillQueryMetrics(SeoProject $project, string $query, Carbon $end, int $days, int $dailyClicks): void
    {
        for ($i = 0; $i < $days; $i++) {
            $date = $end->clone()->subDays($i);
            SeoQueryMetric::create([
                'seo_project_id' => $project->id, 'date' => $date->toDateString(), 'query' => $query,
                'query_hash' => sha1($query), 'clicks' => $dailyClicks, 'impressions' => $dailyClicks * 10,
                'ctr' => 0.1, 'position' => 8.0,
            ]);
        }
    }

    private function fillDailyMetrics(SeoProject $project, Carbon $end, int $days, int $dailyClicks): void
    {
        for ($i = 0; $i < $days; $i++) {
            $date = $end->clone()->subDays($i);
            SeoDailyMetric::create([
                'seo_project_id' => $project->id, 'date' => $date->toDateString(),
                'clicks' => $dailyClicks, 'impressions' => $dailyClicks * 10, 'ctr' => 0.1, 'position' => 8.0,
            ]);
        }
    }

    public function test_capturing_a_baseline_uses_the_subject_query_when_present(): void
    {
        Carbon::setTestNow('2026-09-28 12:00:00');

        $project = SeoProject::factory()->create();
        $opportunity = SeoOpportunity::factory()->create([
            'seo_project_id' => $project->id,
            'subject_query' => 'تله کابین گنجنامه قیمت',
        ]);

        $this->fillQueryMetrics($project, 'تله کابین گنجنامه قیمت', Carbon::now(), 28, 2);
        // A different query in the same project must not be counted.
        $this->fillQueryMetrics($project, 'کلمه دیگر', Carbon::now(), 28, 999);

        $outcome = (new OpportunityOutcomeService)->captureBaseline($opportunity);

        $this->assertSame(56, $outcome->baseline_clicks);
        $this->assertSame(SeoOpportunityOutcomeVerdict::Pending, $outcome->verdict);
        $this->assertNull($outcome->followup_measured_at);
    }

    public function test_capturing_a_baseline_falls_back_to_project_wide_daily_metrics_without_a_subject_query(): void
    {
        Carbon::setTestNow('2026-09-28 12:00:00');

        $project = SeoProject::factory()->create();
        $opportunity = SeoOpportunity::factory()->create([
            'seo_project_id' => $project->id,
            'subject_query' => null,
        ]);

        $this->fillDailyMetrics($project, Carbon::now(), 28, 10);

        $outcome = (new OpportunityOutcomeService)->captureBaseline($opportunity);

        $this->assertSame(280, $outcome->baseline_clicks);
    }

    public function test_evaluating_after_a_real_click_increase_records_an_improved_verdict(): void
    {
        Carbon::setTestNow('2026-09-01 12:00:00');

        $project = SeoProject::factory()->create();
        $opportunity = SeoOpportunity::factory()->create([
            'seo_project_id' => $project->id,
            'subject_query' => 'تله کابین گنجنامه قیمت',
        ]);

        $this->fillQueryMetrics($project, 'تله کابین گنجنامه قیمت', Carbon::now(), 28, 2);

        $service = new OpportunityOutcomeService;
        $outcome = $service->captureBaseline($opportunity);

        Carbon::setTestNow('2026-10-01 12:00:00');
        $this->fillQueryMetrics($project, 'تله کابین گنجنامه قیمت', Carbon::now(), 28, 5);

        $service->evaluate($outcome->fresh());

        $this->assertSame(SeoOpportunityOutcomeVerdict::Improved, $outcome->fresh()->verdict);
        $this->assertNotNull($outcome->fresh()->followup_measured_at);
    }

    public function test_evaluating_after_a_real_click_decrease_records_a_declined_verdict(): void
    {
        Carbon::setTestNow('2026-09-01 12:00:00');

        $project = SeoProject::factory()->create();
        $opportunity = SeoOpportunity::factory()->create([
            'seo_project_id' => $project->id,
            'subject_query' => 'تله کابین گنجنامه قیمت',
        ]);

        $this->fillQueryMetrics($project, 'تله کابین گنجنامه قیمت', Carbon::now(), 28, 10);

        $service = new OpportunityOutcomeService;
        $outcome = $service->captureBaseline($opportunity);

        Carbon::setTestNow('2026-10-01 12:00:00');
        $this->fillQueryMetrics($project, 'تله کابین گنجنامه قیمت', Carbon::now(), 28, 2);

        $service->evaluate($outcome->fresh());

        $this->assertSame(SeoOpportunityOutcomeVerdict::Declined, $outcome->fresh()->verdict);
    }

    public function test_evaluating_with_no_meaningful_change_records_an_unchanged_verdict(): void
    {
        Carbon::setTestNow('2026-09-01 12:00:00');

        $project = SeoProject::factory()->create();
        $opportunity = SeoOpportunity::factory()->create([
            'seo_project_id' => $project->id,
            'subject_query' => 'تله کابین گنجنامه قیمت',
        ]);

        $this->fillQueryMetrics($project, 'تله کابین گنجنامه قیمت', Carbon::now(), 28, 10);

        $service = new OpportunityOutcomeService;
        $outcome = $service->captureBaseline($opportunity);

        Carbon::setTestNow('2026-10-01 12:00:00');
        $this->fillQueryMetrics($project, 'تله کابین گنجنامه قیمت', Carbon::now(), 28, 10);

        $service->evaluate($outcome->fresh());

        $this->assertSame(SeoOpportunityOutcomeVerdict::Unchanged, $outcome->fresh()->verdict);
    }
}
