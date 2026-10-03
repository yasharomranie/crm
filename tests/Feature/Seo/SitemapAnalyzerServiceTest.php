<?php

namespace Tests\Feature\Seo;

use App\Domain\Seo\SitemapAnalyzerService;
use App\Models\SeoPageMetric;
use App\Models\SeoProject;
use App\Models\SeoSitemapEntry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SitemapAnalyzerServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_overview_counts_total_duplicates_and_missing_lastmod(): void
    {
        $project = SeoProject::factory()->create();

        SeoSitemapEntry::factory()->create(['seo_project_id' => $project->id, 'url' => 'https://ganjnameh.example.com/telecabin', 'lastmod' => now()]);
        // Same page, differs only by scheme/www/trailing-slash - a real duplicate.
        SeoSitemapEntry::factory()->create(['seo_project_id' => $project->id, 'url' => 'http://www.ganjnameh.example.com/telecabin/', 'lastmod' => now()]);
        SeoSitemapEntry::factory()->create(['seo_project_id' => $project->id, 'url' => 'https://ganjnameh.example.com/about', 'lastmod' => null]);

        $overview = app(SitemapAnalyzerService::class)->overview($project);

        $this->assertSame(3, $overview['total']);
        $this->assertSame(2, $overview['duplicates']);
        $this->assertSame(1, $overview['missing_lastmod']);
    }

    public function test_overview_classifies_content_types_from_the_url_path(): void
    {
        $project = SeoProject::factory()->create();

        SeoSitemapEntry::factory()->create(['seo_project_id' => $project->id, 'url' => 'https://ganjnameh.example.com/']);
        SeoSitemapEntry::factory()->create(['seo_project_id' => $project->id, 'url' => 'https://ganjnameh.example.com/product/telecabin-ticket']);
        SeoSitemapEntry::factory()->create(['seo_project_id' => $project->id, 'url' => 'https://ganjnameh.example.com/blog/best-time-to-visit']);
        SeoSitemapEntry::factory()->create(['seo_project_id' => $project->id, 'url' => 'https://ganjnameh.example.com/random-page']);

        $overview = app(SitemapAnalyzerService::class)->overview($project);

        $this->assertSame(1, $overview['content_types']['صفحه اصلی']);
        $this->assertSame(1, $overview['content_types']['محصول']);
        $this->assertSame(1, $overview['content_types']['پست وبلاگ']);
        $this->assertSame(1, $overview['content_types']['صفحه']);
    }

    public function test_overview_buckets_entries_by_lastmod_age(): void
    {
        $project = SeoProject::factory()->create();

        SeoSitemapEntry::factory()->create(['seo_project_id' => $project->id, 'lastmod' => now()->subDays(3)]);
        SeoSitemapEntry::factory()->create(['seo_project_id' => $project->id, 'lastmod' => now()->subDays(45)]);
        SeoSitemapEntry::factory()->create(['seo_project_id' => $project->id, 'lastmod' => now()->subDays(400)]);

        $overview = app(SitemapAnalyzerService::class)->overview($project);

        $this->assertSame(1, $overview['lastmod_buckets']['0 تا 7 روز']);
        $this->assertSame(1, $overview['lastmod_buckets']['31 تا 90 روز']);
        $this->assertSame(1, $overview['lastmod_buckets']['بیش از 180 روز']);
    }

    public function test_growth_series_counts_entries_by_the_month_they_were_first_seen(): void
    {
        $project = SeoProject::factory()->create();

        SeoSitemapEntry::factory()->create(['seo_project_id' => $project->id, 'first_seen_at' => now()]);
        SeoSitemapEntry::factory()->create(['seo_project_id' => $project->id, 'first_seen_at' => now()]);
        SeoSitemapEntry::factory()->create(['seo_project_id' => $project->id, 'first_seen_at' => now()->subMonths(2)]);

        $series = app(SitemapAnalyzerService::class)->growthSeries($project, 3);

        $this->assertCount(3, $series);
        $this->assertSame(2, $series[2]['count']);
        $this->assertSame(0, $series[1]['count']);
        $this->assertSame(1, $series[0]['count']);
    }

    public function test_pages_joins_each_entry_with_its_trailing_28_day_search_console_numbers(): void
    {
        $project = SeoProject::factory()->create();
        $url = 'https://ganjnameh.example.com/telecabin';

        SeoSitemapEntry::factory()->create(['seo_project_id' => $project->id, 'url' => $url, 'title' => 'تله‌کابین گنجنامه']);

        SeoPageMetric::factory()->create([
            'seo_project_id' => $project->id,
            'page_url' => $url,
            'page_url_hash' => sha1($url),
            'date' => now()->subDays(5),
            'clicks' => 10,
            'impressions' => 100,
        ]);
        // Outside the 28-day window - must not be counted.
        SeoPageMetric::factory()->create([
            'seo_project_id' => $project->id,
            'page_url' => $url,
            'page_url_hash' => sha1($url),
            'date' => now()->subDays(60),
            'clicks' => 999,
            'impressions' => 999,
        ]);

        $pages = app(SitemapAnalyzerService::class)->pages($project);

        $this->assertCount(1, $pages);
        $this->assertSame(10, $pages->first()['clicks']);
        $this->assertSame(100, $pages->first()['impressions']);
    }

    public function test_pages_leaves_clicks_null_for_a_page_with_no_search_console_data_yet(): void
    {
        $project = SeoProject::factory()->create();
        SeoSitemapEntry::factory()->create(['seo_project_id' => $project->id, 'url' => 'https://ganjnameh.example.com/brand-new']);

        $pages = app(SitemapAnalyzerService::class)->pages($project);

        $this->assertNull($pages->first()['clicks']);
        $this->assertNull($pages->first()['impressions']);
    }
}
