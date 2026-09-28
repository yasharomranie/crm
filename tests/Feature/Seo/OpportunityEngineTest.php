<?php

namespace Tests\Feature\Seo;

use App\Domain\Seo\KeywordIntelligenceService;
use App\Domain\Seo\OpportunityEngine;
use App\Enums\SeoCannibalizationRisk;
use App\Enums\SeoOpportunityStatus;
use App\Enums\SeoOpportunityType;
use App\Models\SeoDailyMetric;
use App\Models\SeoProject;
use App\Models\SeoQueryMetric;
use App\Models\SeoQueryPageMetric;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class OpportunityEngineTest extends TestCase
{
    use RefreshDatabase;

    /**
     * A perfectly flat baseline has zero variance, which trips the
     * detector's own "not enough real variance to z-score" guard - so
     * this alternates the daily click count slightly, like real traffic
     * would, instead of repeating one exact number.
     */
    private function fillDailyMetrics(SeoProject $project, Carbon $referenceDate, int $days, int $clicksPerDay): void
    {
        for ($i = $days - 1; $i >= 0; $i--) {
            $date = $referenceDate->clone()->subDays($i);
            $clicks = $clicksPerDay + ($i % 2 === 0 ? 2 : -2);

            SeoDailyMetric::create([
                'seo_project_id' => $project->id,
                'date' => $date->toDateString(),
                'clicks' => $clicks,
                'impressions' => $clicks * 20,
                'ctr' => 0.05,
                'position' => 10,
            ]);
        }
    }

    public function test_it_flags_a_ctr_opportunity_for_a_well_ranked_underperforming_keyword(): void
    {
        $project = SeoProject::factory()->create();
        $query = 'تله کابین گنجنامه قیمت';

        for ($i = 0; $i < 28; $i++) {
            $date = Carbon::parse('2026-09-28')->subDays($i);
            SeoQueryMetric::create([
                'seo_project_id' => $project->id, 'date' => $date->toDateString(), 'query' => $query,
                'query_hash' => sha1($query), 'clicks' => 1, 'impressions' => 100, 'ctr' => 0.01, 'position' => 8.0,
            ]);
        }

        (new KeywordIntelligenceService)->rebuildProfiles($project);
        (new OpportunityEngine)->scan($project);

        $opportunity = $project->opportunities()->where('type', SeoOpportunityType::CtrOpportunity->value)->firstOrFail();

        $this->assertSame($query, $opportunity->subject_query);
        $this->assertSame(SeoOpportunityStatus::Detected, $opportunity->status);
        $this->assertSame($query, $opportunity->evidence['keyword']);
        $this->assertGreaterThan(0, $opportunity->evidence['estimated_monthly_clicks_if_fixed']);

        $profile = $project->keywordProfiles()->where('query', $query)->firstOrFail();
        $this->assertNotNull($profile->opportunity_score);
    }

    public function test_it_does_not_flag_a_keyword_whose_ctr_already_meets_the_benchmark(): void
    {
        $project = SeoProject::factory()->create();
        $query = 'کلمه خوب';

        SeoQueryMetric::create([
            'seo_project_id' => $project->id, 'date' => '2026-09-28', 'query' => $query,
            'query_hash' => sha1($query), 'clicks' => 30, 'impressions' => 100, 'ctr' => 0.30, 'position' => 8.0,
        ]);

        (new KeywordIntelligenceService)->rebuildProfiles($project);
        (new OpportunityEngine)->scan($project);

        $this->assertSame(0, $project->opportunities()->count());
    }

    public function test_it_diagnoses_a_traffic_drop_with_a_causal_chain(): void
    {
        $project = SeoProject::factory()->create();
        $referenceDate = Carbon::parse('2026-09-28');

        for ($i = 27; $i >= 0; $i--) {
            $date = $referenceDate->clone()->subDays($i);
            $clicks = $i <= 6 ? 25 : 50;
            SeoDailyMetric::create(['seo_project_id' => $project->id, 'date' => $date->toDateString(), 'clicks' => $clicks, 'impressions' => $clicks * 20, 'ctr' => 0.05, 'position' => 10]);
        }

        (new OpportunityEngine)->scan($project);

        $opportunity = $project->opportunities()->where('type', SeoOpportunityType::TrafficDrop->value)->firstOrFail();

        $this->assertEqualsWithDelta(-50.0, $opportunity->evidence['percent_change'], 0.5);
        $this->assertSame(175, $opportunity->evidence['current_week_clicks']);
        $this->assertSame(350, $opportunity->evidence['previous_week_clicks']);
    }

    public function test_it_ignores_a_decline_below_the_minimum_baseline_to_avoid_noise(): void
    {
        $project = SeoProject::factory()->create();
        $referenceDate = Carbon::parse('2026-09-28');

        // Previous week baseline (7) is below the minimum-baseline guard.
        for ($i = 13; $i >= 7; $i--) {
            SeoDailyMetric::create(['seo_project_id' => $project->id, 'date' => $referenceDate->clone()->subDays($i)->toDateString(), 'clicks' => 1, 'impressions' => 20, 'ctr' => 0.05, 'position' => 10]);
        }
        for ($i = 6; $i >= 0; $i--) {
            SeoDailyMetric::create(['seo_project_id' => $project->id, 'date' => $referenceDate->clone()->subDays($i)->toDateString(), 'clicks' => 0, 'impressions' => 5, 'ctr' => 0, 'position' => 10]);
        }

        (new OpportunityEngine)->scan($project);

        $this->assertSame(0, $project->opportunities()->where('type', SeoOpportunityType::TrafficDrop->value)->count());
    }

    public function test_it_flags_a_positive_anomaly_on_an_unusual_spike_day(): void
    {
        $project = SeoProject::factory()->create();
        $referenceDate = Carbon::parse('2026-09-28');

        $this->fillDailyMetrics($project, $referenceDate->clone()->subDay(), 28, 50);
        SeoDailyMetric::create(['seo_project_id' => $project->id, 'date' => $referenceDate->toDateString(), 'clicks' => 500, 'impressions' => 5000, 'ctr' => 0.1, 'position' => 5]);

        (new OpportunityEngine)->scan($project);

        $opportunity = $project->opportunities()->where('type', SeoOpportunityType::AnomalyPositive->value)->firstOrFail();

        $this->assertSame('2026-09-28', $opportunity->evidence['date']);
        $this->assertGreaterThan(2.0, $opportunity->evidence['z_score']);
    }

    public function test_it_does_not_flag_an_anomaly_with_too_little_history(): void
    {
        $project = SeoProject::factory()->create();
        $this->fillDailyMetrics($project, Carbon::parse('2026-09-28'), 5, 50);

        (new OpportunityEngine)->scan($project);

        $this->assertSame(0, $project->opportunities()->whereIn('type', [
            SeoOpportunityType::AnomalyPositive->value,
            SeoOpportunityType::AnomalyNegative->value,
        ])->count());
    }

    public function test_it_flags_high_risk_cannibalization_when_two_pages_split_clicks_evenly(): void
    {
        $project = SeoProject::factory()->create();
        $query = 'رزرو ویلا گنجنامه';
        $pageA = 'https://ganjnameh.example.com/villas';
        $pageB = 'https://ganjnameh.example.com/villas-booking';

        for ($i = 27; $i >= 0; $i--) {
            $date = Carbon::parse('2026-09-28')->subDays($i);
            SeoQueryPageMetric::create(['seo_project_id' => $project->id, 'date' => $date->toDateString(), 'query' => $query, 'query_hash' => sha1($query), 'page_url' => $pageA, 'page_url_hash' => sha1($pageA), 'clicks' => 3, 'impressions' => 40, 'ctr' => 0.075, 'position' => 6]);
            SeoQueryPageMetric::create(['seo_project_id' => $project->id, 'date' => $date->toDateString(), 'query' => $query, 'query_hash' => sha1($query), 'page_url' => $pageB, 'page_url_hash' => sha1($pageB), 'clicks' => 2, 'impressions' => 35, 'ctr' => 0.057, 'position' => 8]);
            SeoQueryMetric::create(['seo_project_id' => $project->id, 'date' => $date->toDateString(), 'query' => $query, 'query_hash' => sha1($query), 'clicks' => 5, 'impressions' => 75, 'ctr' => 0.067, 'position' => 7]);
        }

        (new KeywordIntelligenceService)->rebuildProfiles($project);
        (new OpportunityEngine)->scan($project);

        $opportunity = $project->opportunities()->where('type', SeoOpportunityType::Cannibalization->value)->firstOrFail();
        $this->assertSame($query, $opportunity->subject_query);
        $this->assertCount(2, $opportunity->evidence['pages']);

        $profile = $project->keywordProfiles()->where('query', $query)->firstOrFail();
        $this->assertSame(SeoCannibalizationRisk::High, $profile->cannibalization_risk);
    }

    public function test_it_does_not_flag_cannibalization_for_a_single_dominant_page(): void
    {
        $project = SeoProject::factory()->create();
        $query = 'کلمه تک صفحه';
        $page = 'https://ganjnameh.example.com/only-page';

        SeoQueryPageMetric::create(['seo_project_id' => $project->id, 'date' => '2026-09-28', 'query' => $query, 'query_hash' => sha1($query), 'page_url' => $page, 'page_url_hash' => sha1($page), 'clicks' => 50, 'impressions' => 500, 'ctr' => 0.1, 'position' => 3]);

        (new OpportunityEngine)->scan($project);

        $this->assertSame(0, $project->opportunities()->where('type', SeoOpportunityType::Cannibalization->value)->count());
    }

    public function test_re_scanning_refreshes_an_open_opportunity_instead_of_duplicating_it(): void
    {
        $project = SeoProject::factory()->create();
        $query = 'کلمه تکراری';

        for ($i = 0; $i < 5; $i++) {
            $date = Carbon::parse('2026-09-28')->subDays($i);
            SeoQueryMetric::create(['seo_project_id' => $project->id, 'date' => $date->toDateString(), 'query' => $query, 'query_hash' => sha1($query), 'clicks' => 1, 'impressions' => 100, 'ctr' => 0.01, 'position' => 8.0]);
        }

        $engine = new OpportunityEngine;
        (new KeywordIntelligenceService)->rebuildProfiles($project);
        $engine->scan($project);
        $engine->scan($project);

        $this->assertSame(1, $project->opportunities()->where('type', SeoOpportunityType::CtrOpportunity->value)->count());
    }

    /**
     * Dismissing is a judgment on that one instance, not a permanent
     * mute: dedupe only looks at unresolved (Detected/Reviewed)
     * opportunities, so if the underlying condition is still true on the
     * next scan, a fresh one is raised - like an acknowledged monitoring
     * alert that still re-fires while the condition persists. The
     * dismissed record itself is never touched.
     */
    public function test_a_dismissed_opportunity_is_re_raised_as_a_new_one_if_the_condition_still_holds(): void
    {
        $project = SeoProject::factory()->create();
        $query = 'کلمه رد شده';

        for ($i = 0; $i < 5; $i++) {
            $date = Carbon::parse('2026-09-28')->subDays($i);
            SeoQueryMetric::create(['seo_project_id' => $project->id, 'date' => $date->toDateString(), 'query' => $query, 'query_hash' => sha1($query), 'clicks' => 1, 'impressions' => 100, 'ctr' => 0.01, 'position' => 8.0]);
        }

        (new KeywordIntelligenceService)->rebuildProfiles($project);
        (new OpportunityEngine)->scan($project);

        $dismissed = $project->opportunities()->first();
        $dismissed->update(['status' => SeoOpportunityStatus::Dismissed]);

        (new OpportunityEngine)->scan($project);

        $this->assertSame(2, $project->opportunities()->count());
        $this->assertSame(SeoOpportunityStatus::Dismissed, $dismissed->fresh()->status);
        $this->assertSame(
            1,
            $project->opportunities()->where('status', SeoOpportunityStatus::Detected->value)->count(),
        );
    }
}
