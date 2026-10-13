<?php

namespace Tests\Feature\Seo;

use App\Domain\Seo\UrlOpportunityEngine;
use App\Enums\SeoOpportunityPriority;
use App\Enums\SeoOpportunityStatus;
use App\Enums\SeoOpportunityType;
use App\Models\SeoInternalLink;
use App\Models\SeoPageMetric;
use App\Models\SeoProject;
use App\Models\SeoSitemapEntry;
use App\Models\SeoTechnicalAudit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UrlOpportunityEngineTest extends TestCase
{
    use RefreshDatabase;

    private function fillPageMetrics(SeoProject $project, string $url, int $days, int $dailyClicks, int $dailyImpressions, float $position): void
    {
        for ($i = 0; $i < $days; $i++) {
            SeoPageMetric::create([
                'seo_project_id' => $project->id,
                'date' => now()->subDays($i)->toDateString(),
                'page_url' => $url,
                'page_url_hash' => sha1($url),
                'clicks' => $dailyClicks,
                'impressions' => $dailyImpressions,
                'ctr' => $dailyImpressions > 0 ? $dailyClicks / $dailyImpressions : 0,
                'position' => $position,
            ]);
        }
    }

    public function test_it_flags_a_well_ranked_page_with_low_ctr(): void
    {
        $project = SeoProject::factory()->create();
        $url = 'https://ganjnameh.example.com/telecabin';
        SeoSitemapEntry::factory()->create(['seo_project_id' => $project->id, 'url' => $url]);

        $this->fillPageMetrics($project, $url, 28, dailyClicks: 1, dailyImpressions: 100, position: 8.0);

        app(UrlOpportunityEngine::class)->scan($project);

        $opportunity = $project->opportunities()->where('type', SeoOpportunityType::UrlCtrOpportunity->value)->first();
        $this->assertNotNull($opportunity);
        $this->assertSame($url, $opportunity->subject_page_url);
        $this->assertNull($opportunity->subject_query);
    }

    public function test_it_does_not_flag_a_page_with_too_few_impressions(): void
    {
        $project = SeoProject::factory()->create();
        $url = 'https://ganjnameh.example.com/telecabin';
        SeoSitemapEntry::factory()->create(['seo_project_id' => $project->id, 'url' => $url]);

        $this->fillPageMetrics($project, $url, 28, dailyClicks: 0, dailyImpressions: 2, position: 8.0);

        app(UrlOpportunityEngine::class)->scan($project);

        $this->assertSame(0, $project->opportunities()->where('type', SeoOpportunityType::UrlCtrOpportunity->value)->count());
    }

    public function test_it_flags_an_old_page_with_no_recent_clicks_as_stale(): void
    {
        $project = SeoProject::factory()->create();
        $url = 'https://ganjnameh.example.com/old-page';
        SeoSitemapEntry::factory()->create([
            'seo_project_id' => $project->id,
            'url' => $url,
            'first_seen_at' => now()->subDays(200),
        ]);

        app(UrlOpportunityEngine::class)->scan($project);

        $opportunity = $project->opportunities()->where('type', SeoOpportunityType::StalePage->value)->first();
        $this->assertNotNull($opportunity);
        $this->assertSame(SeoOpportunityPriority::Low, $opportunity->priority);
    }

    public function test_it_does_not_flag_a_stale_page_that_still_gets_clicks(): void
    {
        $project = SeoProject::factory()->create();
        $url = 'https://ganjnameh.example.com/old-but-popular';
        SeoSitemapEntry::factory()->create([
            'seo_project_id' => $project->id,
            'url' => $url,
            'first_seen_at' => now()->subDays(200),
        ]);
        $this->fillPageMetrics($project, $url, 10, dailyClicks: 5, dailyImpressions: 50, position: 4.0);

        app(UrlOpportunityEngine::class)->scan($project);

        $this->assertSame(0, $project->opportunities()->where('type', SeoOpportunityType::StalePage->value)->count());
    }

    public function test_it_does_not_flag_a_brand_new_page_as_stale(): void
    {
        $project = SeoProject::factory()->create();
        SeoSitemapEntry::factory()->create([
            'seo_project_id' => $project->id,
            'first_seen_at' => now()->subDays(5),
        ]);

        app(UrlOpportunityEngine::class)->scan($project);

        $this->assertSame(0, $project->opportunities()->where('type', SeoOpportunityType::StalePage->value)->count());
    }

    public function test_it_flags_a_page_with_no_impressions_after_a_reasonable_crawl_delay_as_invisible(): void
    {
        $project = SeoProject::factory()->create();
        $url = 'https://ganjnameh.example.com/invisible-page';
        SeoSitemapEntry::factory()->create([
            'seo_project_id' => $project->id,
            'url' => $url,
            'first_seen_at' => now()->subDays(30),
        ]);

        app(UrlOpportunityEngine::class)->scan($project);

        $opportunity = $project->opportunities()->where('type', SeoOpportunityType::InvisibleNewPage->value)->first();
        $this->assertNotNull($opportunity);
    }

    public function test_it_does_not_flag_a_just_published_page_as_invisible_yet(): void
    {
        $project = SeoProject::factory()->create();
        SeoSitemapEntry::factory()->create([
            'seo_project_id' => $project->id,
            'first_seen_at' => now()->subDays(3),
        ]);

        app(UrlOpportunityEngine::class)->scan($project);

        $this->assertSame(0, $project->opportunities()->where('type', SeoOpportunityType::InvisibleNewPage->value)->count());
    }

    public function test_rescanning_refreshes_an_existing_open_opportunity_instead_of_duplicating(): void
    {
        $project = SeoProject::factory()->create();
        $url = 'https://ganjnameh.example.com/old-page';
        SeoSitemapEntry::factory()->create([
            'seo_project_id' => $project->id,
            'url' => $url,
            'first_seen_at' => now()->subDays(200),
        ]);

        $engine = app(UrlOpportunityEngine::class);
        $engine->scan($project);
        $engine->scan($project);

        $this->assertSame(1, $project->opportunities()->where('type', SeoOpportunityType::StalePage->value)->count());
    }

    public function test_a_resolved_opportunity_is_not_counted_as_existing_so_a_fresh_one_can_be_detected_again(): void
    {
        $project = SeoProject::factory()->create();
        $url = 'https://ganjnameh.example.com/old-page';
        SeoSitemapEntry::factory()->create([
            'seo_project_id' => $project->id,
            'url' => $url,
            'first_seen_at' => now()->subDays(200),
        ]);

        app(UrlOpportunityEngine::class)->scan($project);
        $project->opportunities()->where('type', SeoOpportunityType::StalePage->value)->first()->update([
            'status' => SeoOpportunityStatus::Resolved,
            'resolved_at' => now(),
        ]);

        app(UrlOpportunityEngine::class)->scan($project);

        $this->assertSame(2, $project->opportunities()->where('type', SeoOpportunityType::StalePage->value)->count());
    }

    public function test_a_project_with_no_sitemap_entries_produces_no_url_opportunities(): void
    {
        $project = SeoProject::factory()->create();

        app(UrlOpportunityEngine::class)->scan($project);

        $this->assertSame(0, $project->opportunities()->count());
    }

    /**
     * Regression: the Search Console join used to be an exact sha1()
     * match on the raw URL - a page's real click data went invisible to
     * every detector whenever the sitemap and Google reported the same
     * page with a different trailing slash/scheme/www.
     */
    public function test_it_matches_search_console_data_even_when_the_url_form_differs(): void
    {
        $project = SeoProject::factory()->create();
        SeoSitemapEntry::factory()->create(['seo_project_id' => $project->id, 'url' => 'https://ganjnameh.example.com/telecabin/']);

        // Google reported it without the trailing slash and with www.
        $this->fillPageMetrics($project, 'https://www.ganjnameh.example.com/telecabin', 28, dailyClicks: 1, dailyImpressions: 100, position: 8.0);

        app(UrlOpportunityEngine::class)->scan($project);

        $this->assertNotNull($project->opportunities()->where('type', SeoOpportunityType::UrlCtrOpportunity->value)->first());
    }

    public function test_it_flags_a_high_traffic_page_with_barely_any_incoming_internal_links(): void
    {
        $project = SeoProject::factory()->create();
        $url = 'https://ganjnameh.example.com/popular-page';
        SeoSitemapEntry::factory()->create(['seo_project_id' => $project->id, 'url' => $url]);
        $this->fillPageMetrics($project, $url, 10, dailyClicks: 2, dailyImpressions: 20, position: 4.0);
        SeoTechnicalAudit::factory()->create(['seo_project_id' => $project->id]);

        app(UrlOpportunityEngine::class)->scan($project);

        $opportunity = $project->opportunities()->where('type', SeoOpportunityType::UnderLinkedPage->value)->first();
        $this->assertNotNull($opportunity);
        $this->assertSame($url, $opportunity->subject_page_url);
        $this->assertSame(0, $opportunity->evidence['incoming_internal_links']);
    }

    public function test_it_does_not_flag_a_high_traffic_page_that_already_has_more_than_one_internal_link(): void
    {
        $project = SeoProject::factory()->create();
        $url = 'https://ganjnameh.example.com/well-linked-page';
        SeoSitemapEntry::factory()->create(['seo_project_id' => $project->id, 'url' => $url]);
        $this->fillPageMetrics($project, $url, 10, dailyClicks: 2, dailyImpressions: 20, position: 4.0);
        $audit = SeoTechnicalAudit::factory()->create(['seo_project_id' => $project->id]);
        SeoInternalLink::factory()->create(['seo_technical_audit_id' => $audit->id, 'from_url' => 'https://ganjnameh.example.com/home', 'to_url' => $url]);
        SeoInternalLink::factory()->create(['seo_technical_audit_id' => $audit->id, 'from_url' => 'https://ganjnameh.example.com/menu', 'to_url' => $url]);

        app(UrlOpportunityEngine::class)->scan($project);

        $this->assertSame(0, $project->opportunities()->where('type', SeoOpportunityType::UnderLinkedPage->value)->count());
    }

    public function test_it_does_not_flag_a_low_traffic_page_even_with_no_internal_links(): void
    {
        $project = SeoProject::factory()->create();
        $url = 'https://ganjnameh.example.com/quiet-page';
        SeoSitemapEntry::factory()->create(['seo_project_id' => $project->id, 'url' => $url]);
        $this->fillPageMetrics($project, $url, 10, dailyClicks: 0, dailyImpressions: 5, position: 15.0);
        SeoTechnicalAudit::factory()->create(['seo_project_id' => $project->id]);

        app(UrlOpportunityEngine::class)->scan($project);

        $this->assertSame(0, $project->opportunities()->where('type', SeoOpportunityType::UnderLinkedPage->value)->count());
    }

    public function test_it_does_not_flag_under_linked_pages_without_a_completed_technical_audit(): void
    {
        $project = SeoProject::factory()->create();
        $url = 'https://ganjnameh.example.com/popular-page-no-audit';
        SeoSitemapEntry::factory()->create(['seo_project_id' => $project->id, 'url' => $url]);
        $this->fillPageMetrics($project, $url, 10, dailyClicks: 2, dailyImpressions: 20, position: 4.0);

        app(UrlOpportunityEngine::class)->scan($project);

        $this->assertSame(0, $project->opportunities()->where('type', SeoOpportunityType::UnderLinkedPage->value)->count());
    }
}
