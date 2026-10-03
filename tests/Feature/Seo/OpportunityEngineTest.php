<?php

namespace Tests\Feature\Seo;

use App\Domain\Seo\KeywordIntelligenceService;
use App\Domain\Seo\OpportunityEngine;
use App\Enums\SeoCannibalizationRisk;
use App\Enums\SeoOpportunityStatus;
use App\Enums\SeoOpportunityType;
use App\Models\SeoDailyMetric;
use App\Models\SeoPageMetric;
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
        (app(OpportunityEngine::class))->scan($project);

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
        (app(OpportunityEngine::class))->scan($project);

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

        (app(OpportunityEngine::class))->scan($project);

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

        (app(OpportunityEngine::class))->scan($project);

        $this->assertSame(0, $project->opportunities()->where('type', SeoOpportunityType::TrafficDrop->value)->count());
    }

    public function test_it_flags_a_positive_anomaly_on_an_unusual_spike_day(): void
    {
        $project = SeoProject::factory()->create();
        $referenceDate = Carbon::parse('2026-09-28');

        $this->fillDailyMetrics($project, $referenceDate->clone()->subDay(), 28, 50);
        SeoDailyMetric::create(['seo_project_id' => $project->id, 'date' => $referenceDate->toDateString(), 'clicks' => 500, 'impressions' => 5000, 'ctr' => 0.1, 'position' => 5]);

        (app(OpportunityEngine::class))->scan($project);

        $opportunity = $project->opportunities()->where('type', SeoOpportunityType::AnomalyPositive->value)->firstOrFail();

        $this->assertSame('2026-09-28', $opportunity->evidence['date']);
        $this->assertGreaterThan(2.0, $opportunity->evidence['z_score']);
    }

    public function test_it_does_not_flag_an_anomaly_with_too_little_history(): void
    {
        $project = SeoProject::factory()->create();
        $this->fillDailyMetrics($project, Carbon::parse('2026-09-28'), 5, 50);

        (app(OpportunityEngine::class))->scan($project);

        $this->assertSame(0, $project->opportunities()->whereIn('type', [
            SeoOpportunityType::AnomalyPositive->value,
            SeoOpportunityType::AnomalyNegative->value,
        ])->count());
    }

    /**
     * Regression test for the exact bug a naive date-only dedupe would
     * hit: clicks and impressions both spike on the very same day, so
     * two different anomaly types get recorded for the same project on
     * the same date. Each metric must land in its own row, not have the
     * second detector silently overwrite the first's.
     */
    public function test_a_click_and_an_impression_anomaly_on_the_same_day_are_recorded_separately(): void
    {
        $project = SeoProject::factory()->create();
        $referenceDate = Carbon::parse('2026-09-28');

        for ($i = 14; $i >= 1; $i--) {
            $date = $referenceDate->clone()->subDays($i);
            $clicks = 50 + ($i % 2 === 0 ? 2 : -2);
            SeoDailyMetric::create(['seo_project_id' => $project->id, 'date' => $date->toDateString(), 'clicks' => $clicks, 'impressions' => 1000 + ($i % 2 === 0 ? 40 : -40), 'ctr' => 0.05, 'position' => 10]);
        }
        SeoDailyMetric::create(['seo_project_id' => $project->id, 'date' => $referenceDate->toDateString(), 'clicks' => 500, 'impressions' => 10000, 'ctr' => 0.05, 'position' => 10]);

        (app(OpportunityEngine::class))->scan($project);

        $this->assertSame(1, $project->opportunities()->where('type', SeoOpportunityType::AnomalyPositive->value)->count());
        $this->assertSame(1, $project->opportunities()->where('type', SeoOpportunityType::ImpressionAnomalyPositive->value)->count());
    }

    public function test_it_flags_a_position_anomaly_when_average_rank_suddenly_improves(): void
    {
        $project = SeoProject::factory()->create();
        $referenceDate = Carbon::parse('2026-09-28');

        for ($i = 14; $i >= 1; $i--) {
            $date = $referenceDate->clone()->subDays($i);
            $position = 10 + ($i % 2 === 0 ? 1 : -1);
            SeoDailyMetric::create(['seo_project_id' => $project->id, 'date' => $date->toDateString(), 'clicks' => 50, 'impressions' => 1000, 'ctr' => 0.05, 'position' => $position]);
        }
        // A sudden, sharp improvement (lower number) on the latest day.
        SeoDailyMetric::create(['seo_project_id' => $project->id, 'date' => $referenceDate->toDateString(), 'clicks' => 50, 'impressions' => 1000, 'ctr' => 0.05, 'position' => 2]);

        (app(OpportunityEngine::class))->scan($project);

        $opportunity = $project->opportunities()->where('type', SeoOpportunityType::PositionAnomalyPositive->value)->firstOrFail();
        $this->assertSame('2026-09-28', $opportunity->evidence['date']);
    }

    public function test_it_flags_a_ctr_anomaly_on_an_unusual_drop(): void
    {
        $project = SeoProject::factory()->create();
        $referenceDate = Carbon::parse('2026-09-28');

        for ($i = 14; $i >= 1; $i--) {
            $date = $referenceDate->clone()->subDays($i);
            $ctr = 0.05 + ($i % 2 === 0 ? 0.02 : -0.02);
            SeoDailyMetric::create(['seo_project_id' => $project->id, 'date' => $date->toDateString(), 'clicks' => 50, 'impressions' => 1000, 'ctr' => $ctr, 'position' => 10]);
        }
        SeoDailyMetric::create(['seo_project_id' => $project->id, 'date' => $referenceDate->toDateString(), 'clicks' => 50, 'impressions' => 1000, 'ctr' => 0.001, 'position' => 10]);

        (app(OpportunityEngine::class))->scan($project);

        $opportunity = $project->opportunities()->where('type', SeoOpportunityType::CtrAnomalyNegative->value)->firstOrFail();
        $this->assertSame('2026-09-28', $opportunity->evidence['date']);
    }

    public function test_it_flags_a_page_level_anomaly_with_the_correct_subject(): void
    {
        $project = SeoProject::factory()->create();
        $page = 'https://ganjnameh.example.com/telecabin';
        $referenceDate = Carbon::parse('2026-09-28');

        for ($i = 14; $i >= 1; $i--) {
            $date = $referenceDate->clone()->subDays($i);
            $clicks = 20 + ($i % 2 === 0 ? 2 : -2);
            SeoPageMetric::create(['seo_project_id' => $project->id, 'date' => $date->toDateString(), 'page_url' => $page, 'page_url_hash' => sha1($page), 'clicks' => $clicks, 'impressions' => 400, 'ctr' => 0.05, 'position' => 6]);
        }
        SeoPageMetric::create(['seo_project_id' => $project->id, 'date' => $referenceDate->toDateString(), 'page_url' => $page, 'page_url_hash' => sha1($page), 'clicks' => 1, 'impressions' => 400, 'ctr' => 0.0025, 'position' => 6]);

        (app(OpportunityEngine::class))->scan($project);

        $opportunity = $project->opportunities()->where('type', SeoOpportunityType::PageAnomalyNegative->value)->firstOrFail();
        $this->assertSame($page, $opportunity->subject_page_url);
    }

    public function test_it_flags_a_query_level_anomaly_with_the_correct_subject(): void
    {
        $project = SeoProject::factory()->create();
        $query = 'کرایه تله کابین';
        $referenceDate = Carbon::parse('2026-09-28');

        for ($i = 14; $i >= 1; $i--) {
            $date = $referenceDate->clone()->subDays($i);
            $clicks = 20 + ($i % 2 === 0 ? 2 : -2);
            SeoQueryMetric::create(['seo_project_id' => $project->id, 'date' => $date->toDateString(), 'query' => $query, 'query_hash' => sha1($query), 'clicks' => $clicks, 'impressions' => 400, 'ctr' => 0.05, 'position' => 6]);
        }
        SeoQueryMetric::create(['seo_project_id' => $project->id, 'date' => $referenceDate->toDateString(), 'query' => $query, 'query_hash' => sha1($query), 'clicks' => 100, 'impressions' => 400, 'ctr' => 0.25, 'position' => 6]);

        (app(OpportunityEngine::class))->scan($project);

        $opportunity = $project->opportunities()->where('type', SeoOpportunityType::QueryAnomalyPositive->value)->firstOrFail();
        $this->assertSame($query, $opportunity->subject_query);
    }

    /**
     * The exact noise filter the feature was built for: a low-traffic
     * page can swing wildly in percentage terms without ever being worth
     * an operator's attention. Fifteen much busier pages fill the
     * candidate pool first, so the spiking long-tail page - ranked 16th
     * by recent volume - never gets its own z-score check at all.
     */
    public function test_it_ignores_a_long_tail_page_outside_the_busiest_candidate_pool(): void
    {
        $project = SeoProject::factory()->create();
        $referenceDate = Carbon::parse('2026-09-28');

        for ($p = 1; $p <= 15; $p++) {
            $page = "https://ganjnameh.example.com/busy-page-{$p}";

            for ($i = 14; $i >= 0; $i--) {
                $date = $referenceDate->clone()->subDays($i);
                SeoPageMetric::create(['seo_project_id' => $project->id, 'date' => $date->toDateString(), 'page_url' => $page, 'page_url_hash' => sha1($page), 'clicks' => 10, 'impressions' => 200, 'ctr' => 0.05, 'position' => 6]);
            }
        }

        $quietPage = 'https://ganjnameh.example.com/quiet-page';
        for ($i = 14; $i >= 1; $i--) {
            $date = $referenceDate->clone()->subDays($i);
            SeoPageMetric::create(['seo_project_id' => $project->id, 'date' => $date->toDateString(), 'page_url' => $quietPage, 'page_url_hash' => sha1($quietPage), 'clicks' => 1, 'impressions' => 20, 'ctr' => 0.05, 'position' => 15]);
        }
        // A genuine 50x spike relative to its own baseline - but its
        // window total (14 + 50 = 64) still stays far below each of the
        // 15 busy pages' totals (150 each), so it never enters the pool.
        SeoPageMetric::create(['seo_project_id' => $project->id, 'date' => $referenceDate->toDateString(), 'page_url' => $quietPage, 'page_url_hash' => sha1($quietPage), 'clicks' => 50, 'impressions' => 200, 'ctr' => 0.25, 'position' => 15]);

        (app(OpportunityEngine::class))->scan($project);

        $this->assertSame(0, $project->opportunities()->where('subject_page_url', $quietPage)->count());
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
        (app(OpportunityEngine::class))->scan($project);

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

        (app(OpportunityEngine::class))->scan($project);

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

        $engine = app(OpportunityEngine::class);
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
        (app(OpportunityEngine::class))->scan($project);

        $dismissed = $project->opportunities()->first();
        $dismissed->update(['status' => SeoOpportunityStatus::Dismissed]);

        (app(OpportunityEngine::class))->scan($project);

        $this->assertSame(2, $project->opportunities()->count());
        $this->assertSame(SeoOpportunityStatus::Dismissed, $dismissed->fresh()->status);
        $this->assertSame(
            1,
            $project->opportunities()->where('status', SeoOpportunityStatus::Detected->value)->count(),
        );
    }
}
