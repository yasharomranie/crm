<?php

namespace Tests\Feature\Seo;

use App\Domain\Seo\KeywordIntelligenceService;
use App\Enums\SeoKeywordTrend;
use App\Models\SeoProject;
use App\Models\SeoQueryMetric;
use App\Models\SeoQueryPageMetric;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class KeywordIntelligenceServiceTest extends TestCase
{
    use RefreshDatabase;

    private const QUERY = 'تله کابین گنجنامه';

    public function test_it_computes_weighted_position_clicks_and_ctr_over_the_current_28_day_window(): void
    {
        $project = SeoProject::factory()->create();

        SeoQueryMetric::create(['seo_project_id' => $project->id, 'date' => '2026-09-01', 'query' => self::QUERY, 'query_hash' => sha1(self::QUERY), 'clicks' => 10, 'impressions' => 200, 'ctr' => 0.05, 'position' => 8.0]);
        SeoQueryMetric::create(['seo_project_id' => $project->id, 'date' => '2026-09-02', 'query' => self::QUERY, 'query_hash' => sha1(self::QUERY), 'clicks' => 15, 'impressions' => 300, 'ctr' => 0.05, 'position' => 6.0]);

        (new KeywordIntelligenceService)->rebuildProfiles($project);

        $profile = $project->keywordProfiles()->firstOrFail();

        $this->assertSame('6.80', $profile->current_position);
        $this->assertSame(25, $profile->clicks_28d);
        $this->assertSame(500, $profile->impressions_28d);
        $this->assertSame('0.0500', $profile->ctr_28d);
    }

    public function test_it_classifies_trend_by_comparing_the_current_and_previous_28_day_windows(): void
    {
        $project = SeoProject::factory()->create();

        // Previous window: worse position (higher number).
        SeoQueryMetric::create(['seo_project_id' => $project->id, 'date' => '2026-07-20', 'query' => self::QUERY, 'query_hash' => sha1(self::QUERY), 'clicks' => 2, 'impressions' => 100, 'ctr' => 0.02, 'position' => 15.0]);
        // Current window: improved position.
        SeoQueryMetric::create(['seo_project_id' => $project->id, 'date' => '2026-09-02', 'query' => self::QUERY, 'query_hash' => sha1(self::QUERY), 'clicks' => 15, 'impressions' => 300, 'ctr' => 0.05, 'position' => 6.0]);

        (new KeywordIntelligenceService)->rebuildProfiles($project);

        $profile = $project->keywordProfiles()->firstOrFail();

        $this->assertSame('15.00', $profile->previous_position);
        $this->assertSame(SeoKeywordTrend::Rising, $profile->trend);
    }

    public function test_a_keyword_with_no_previous_window_data_is_classified_as_new(): void
    {
        $project = SeoProject::factory()->create();

        SeoQueryMetric::create(['seo_project_id' => $project->id, 'date' => '2026-09-02', 'query' => self::QUERY, 'query_hash' => sha1(self::QUERY), 'clicks' => 15, 'impressions' => 300, 'ctr' => 0.05, 'position' => 6.0]);

        (new KeywordIntelligenceService)->rebuildProfiles($project);

        $this->assertSame(SeoKeywordTrend::New, $project->keywordProfiles()->firstOrFail()->trend);
    }

    public function test_it_picks_the_highest_click_page_as_the_landing_page(): void
    {
        $project = SeoProject::factory()->create();

        SeoQueryMetric::create(['seo_project_id' => $project->id, 'date' => '2026-09-02', 'query' => self::QUERY, 'query_hash' => sha1(self::QUERY), 'clicks' => 15, 'impressions' => 300, 'ctr' => 0.05, 'position' => 6.0]);

        SeoQueryPageMetric::create(['seo_project_id' => $project->id, 'date' => '2026-09-02', 'query' => self::QUERY, 'query_hash' => sha1(self::QUERY), 'page_url' => 'https://ganjnameh.example.com/weak-page', 'page_url_hash' => sha1('https://ganjnameh.example.com/weak-page'), 'clicks' => 3, 'impressions' => 100, 'ctr' => 0.03, 'position' => 9]);
        SeoQueryPageMetric::create(['seo_project_id' => $project->id, 'date' => '2026-09-02', 'query' => self::QUERY, 'query_hash' => sha1(self::QUERY), 'page_url' => 'https://ganjnameh.example.com/telecabin', 'page_url_hash' => sha1('https://ganjnameh.example.com/telecabin'), 'clicks' => 12, 'impressions' => 200, 'ctr' => 0.06, 'position' => 5]);

        (new KeywordIntelligenceService)->rebuildProfiles($project);

        $this->assertSame('https://ganjnameh.example.com/telecabin', $project->keywordProfiles()->firstOrFail()->landing_page);
    }

    public function test_rebuilding_twice_upserts_instead_of_duplicating_profiles(): void
    {
        $project = SeoProject::factory()->create();
        SeoQueryMetric::create(['seo_project_id' => $project->id, 'date' => '2026-09-02', 'query' => self::QUERY, 'query_hash' => sha1(self::QUERY), 'clicks' => 15, 'impressions' => 300, 'ctr' => 0.05, 'position' => 6.0]);

        $service = new KeywordIntelligenceService;
        $service->rebuildProfiles($project);
        $service->rebuildProfiles($project);

        $this->assertSame(1, $project->keywordProfiles()->count());
    }

    public function test_a_query_with_no_impressions_in_the_current_window_gets_no_profile(): void
    {
        $project = SeoProject::factory()->create();

        // Only historical data, nothing in the last 28 days relative to
        // this row's own date being the reference date - i.e. no current
        // window data at all for a *different*, older-only keyword.
        SeoQueryMetric::create(['seo_project_id' => $project->id, 'date' => '2026-09-02', 'query' => 'کلمه فعال', 'query_hash' => sha1('کلمه فعال'), 'clicks' => 15, 'impressions' => 300, 'ctr' => 0.05, 'position' => 6.0]);
        SeoQueryMetric::create(['seo_project_id' => $project->id, 'date' => '2026-06-01', 'query' => 'کلمه قدیمی', 'query_hash' => sha1('کلمه قدیمی'), 'clicks' => 5, 'impressions' => 100, 'ctr' => 0.05, 'position' => 10.0]);

        (new KeywordIntelligenceService)->rebuildProfiles($project);

        $this->assertSame(['کلمه فعال'], $project->keywordProfiles()->pluck('query')->all());
    }
}
