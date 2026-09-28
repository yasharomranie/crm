<?php

namespace Tests\Feature\Seo;

use App\Domain\Seo\ContentPerformanceService;
use App\Enums\SeoContentItemStatus;
use App\Models\SeoContentItem;
use App\Models\SeoPageMetric;
use App\Models\SeoProject;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class ContentPerformanceServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_returns_nulls_when_the_item_has_no_published_url_or_date(): void
    {
        $item = SeoContentItem::factory()->create([
            'status' => SeoContentItemStatus::Writing,
            'published_url' => null,
            'published_at' => null,
        ]);

        $summary = (new ContentPerformanceService)->summarize($item);

        $this->assertSame(0, $summary['clicks']);
        $this->assertSame(0, $summary['impressions']);
        $this->assertNull($summary['avg_position']);
        $this->assertNull($summary['days_since_publish']);
    }

    public function test_it_sums_metrics_only_on_or_after_the_publish_date_for_the_matching_url(): void
    {
        $project = SeoProject::factory()->create();
        $publishedAt = Carbon::parse('2026-09-20 10:00:00');

        $item = SeoContentItem::factory()->create([
            'seo_project_id' => $project->id,
            'status' => SeoContentItemStatus::Published,
            'published_url' => 'https://ganjnameh.example.com/guide',
            'published_at' => $publishedAt,
        ]);

        // Before publish - excluded.
        SeoPageMetric::create([
            'seo_project_id' => $project->id, 'date' => '2026-09-18', 'page_url' => 'https://ganjnameh.example.com/guide',
            'page_url_hash' => sha1('https://ganjnameh.example.com/guide'), 'clicks' => 100, 'impressions' => 1000, 'ctr' => 0.1, 'position' => 20,
        ]);
        // On publish day - included.
        SeoPageMetric::create([
            'seo_project_id' => $project->id, 'date' => '2026-09-20', 'page_url' => 'https://ganjnameh.example.com/guide',
            'page_url_hash' => sha1('https://ganjnameh.example.com/guide'), 'clicks' => 5, 'impressions' => 50, 'ctr' => 0.1, 'position' => 15,
        ]);
        // After publish - included.
        SeoPageMetric::create([
            'seo_project_id' => $project->id, 'date' => '2026-09-22', 'page_url' => 'https://ganjnameh.example.com/guide',
            'page_url_hash' => sha1('https://ganjnameh.example.com/guide'), 'clicks' => 10, 'impressions' => 80, 'ctr' => 0.1, 'position' => 8,
        ]);
        // A different page - excluded.
        SeoPageMetric::create([
            'seo_project_id' => $project->id, 'date' => '2026-09-22', 'page_url' => 'https://ganjnameh.example.com/other',
            'page_url_hash' => sha1('https://ganjnameh.example.com/other'), 'clicks' => 999, 'impressions' => 999, 'ctr' => 0.1, 'position' => 1,
        ]);

        $summary = (new ContentPerformanceService)->summarize($item);

        $this->assertSame(15, $summary['clicks']);
        $this->assertSame(130, $summary['impressions']);
        $this->assertSame(11.5, $summary['avg_position']);
        $this->assertNotNull($summary['days_since_publish']);
    }
}
