<?php

namespace Tests\Feature\Seo;

use App\Domain\Seo\CompetitorSitemapCrawler;
use App\Models\SeoCompetitorSitemapEntry;
use App\Models\SeoProjectCompetitor;
use App\Models\SeoTopicCluster;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class CompetitorSitemapCrawlerTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_it_creates_an_entry_per_url_and_fetches_its_title(): void
    {
        $competitor = SeoProjectCompetitor::factory()->create([
            'domain' => 'competitor.example.com',
            'sitemap_url' => 'https://competitor.example.com/sitemap.xml',
        ]);

        Http::fake([
            'competitor.example.com/sitemap.xml' => Http::response(<<<'XML'
                <?xml version="1.0" encoding="UTF-8"?>
                <urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
                    <url>
                        <loc>https://competitor.example.com/eco-tour</loc>
                        <lastmod>2026-09-20T10:00:00+00:00</lastmod>
                    </url>
                </urlset>
                XML, 200),
            'competitor.example.com/eco-tour' => Http::response(
                '<html><head><title>تور طبیعت‌گردی همدان</title></head><body>ok</body></html>',
                200,
                ['Content-Type' => 'text/html'],
            ),
        ]);

        $newPageCount = app(CompetitorSitemapCrawler::class)->sync($competitor);

        $entry = SeoCompetitorSitemapEntry::where('seo_project_competitor_id', $competitor->id)->first();

        $this->assertNotNull($entry);
        $this->assertSame('https://competitor.example.com/eco-tour', $entry->url);
        $this->assertSame('تور طبیعت‌گردی همدان', $entry->title);
        $this->assertNull($entry->classified_at);
        $this->assertSame(1, $newPageCount);
    }

    public function test_it_throws_when_no_sitemap_can_be_resolved(): void
    {
        $competitor = SeoProjectCompetitor::factory()->create(['domain' => 'competitor.example.com', 'sitemap_url' => null]);

        Http::fake(fn () => Http::response('Not Found', 404));

        $this->expectException(\RuntimeException::class);

        app(CompetitorSitemapCrawler::class)->sync($competitor);
    }

    public function test_an_edited_page_is_reset_for_reclassification(): void
    {
        $competitor = SeoProjectCompetitor::factory()->create([
            'domain' => 'competitor.example.com',
            'sitemap_url' => 'https://competitor.example.com/sitemap.xml',
        ]);
        $cluster = SeoTopicCluster::factory()->for($competitor->project, 'project')->create();

        Carbon::setTestNow('2026-09-21 08:00:00');
        $existing = SeoCompetitorSitemapEntry::factory()->create([
            'seo_project_competitor_id' => $competitor->id,
            'url' => 'https://competitor.example.com/eco-tour',
            'url_hash' => sha1('https://competitor.example.com/eco-tour'),
            'lastmod' => '2026-09-20 10:00:00',
            'topic_cluster_id' => $cluster->id,
            'classified_at' => now(),
        ]);

        Carbon::setTestNow('2026-09-29 08:00:00');

        Http::fake([
            'competitor.example.com/sitemap.xml' => Http::response(<<<'XML'
                <?xml version="1.0" encoding="UTF-8"?>
                <urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
                    <url>
                        <loc>https://competitor.example.com/eco-tour</loc>
                        <lastmod>2026-09-28T10:00:00+00:00</lastmod>
                    </url>
                </urlset>
                XML, 200),
        ]);

        $newPageCount = app(CompetitorSitemapCrawler::class)->sync($competitor);

        $existing->refresh();
        $this->assertNull($existing->classified_at);
        $this->assertNull($existing->topic_cluster_id);
        $this->assertTrue($existing->wasEdited());
        // Re-visiting a known URL (even an edited one) is never counted as
        // "new" - that count exists only to decide whether to alert about
        // pages a competitor didn't have before (App\Domain\Seo\
        // SeoAlertService::notifyCompetitorActivity).
        $this->assertSame(0, $newPageCount);
    }
}
