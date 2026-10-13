<?php

namespace Tests\Feature\Seo;

use App\Domain\Seo\SitemapMonitorService;
use App\Models\SeoProject;
use App\Models\SeoSitemapEntry;
use App\Models\SeoTopicCluster;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SitemapMonitorServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_the_first_sync_creates_an_entry_per_url_and_fetches_its_title(): void
    {
        $project = SeoProject::factory()->create(['sitemap_url' => 'https://ganjnameh.example.com/sitemap.xml']);

        Http::fake([
            'ganjnameh.example.com/sitemap.xml' => Http::response(<<<'XML'
                <?xml version="1.0" encoding="UTF-8"?>
                <urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
                    <url>
                        <loc>https://ganjnameh.example.com/telecabin</loc>
                        <lastmod>2026-09-20T10:00:00+00:00</lastmod>
                    </url>
                </urlset>
                XML, 200),
            'ganjnameh.example.com/telecabin' => Http::response(
                '<html><head><title>تله‌کابین گنجنامه</title></head><body>ok</body></html>',
                200,
                ['Content-Type' => 'text/html'],
            ),
        ]);

        app(SitemapMonitorService::class)->sync($project);

        $entry = SeoSitemapEntry::where('seo_project_id', $project->id)->first();

        $this->assertNotNull($entry);
        $this->assertSame('https://ganjnameh.example.com/telecabin', $entry->url);
        $this->assertSame('تله‌کابین گنجنامه', $entry->title);
        $this->assertNotNull($entry->lastmod);
        $this->assertTrue($entry->first_seen_at->equalTo($entry->last_changed_at));
    }

    public function test_a_second_sync_with_an_unchanged_lastmod_does_not_mark_the_page_as_edited(): void
    {
        $project = SeoProject::factory()->create(['sitemap_url' => 'https://ganjnameh.example.com/sitemap.xml']);

        Http::fake([
            'ganjnameh.example.com/sitemap.xml' => Http::response(<<<'XML'
                <?xml version="1.0" encoding="UTF-8"?>
                <urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
                    <url>
                        <loc>https://ganjnameh.example.com/telecabin</loc>
                        <lastmod>2026-09-20T10:00:00+00:00</lastmod>
                    </url>
                </urlset>
                XML, 200),
            'ganjnameh.example.com/telecabin' => Http::response('<html><head><title>x</title></head></html>', 200, ['Content-Type' => 'text/html']),
        ]);

        $service = app(SitemapMonitorService::class);

        Carbon::setTestNow('2026-09-25 08:00:00');
        $service->sync($project);
        $firstChangedAt = SeoSitemapEntry::where('seo_project_id', $project->id)->first()->last_changed_at;

        Carbon::setTestNow('2026-09-26 08:00:00');
        $service->sync($project);

        $entry = SeoSitemapEntry::where('seo_project_id', $project->id)->first();

        $this->assertSame(1, SeoSitemapEntry::where('seo_project_id', $project->id)->count());
        $this->assertTrue($entry->last_changed_at->equalTo($firstChangedAt));
        $this->assertTrue($entry->last_seen_at->equalTo(Carbon::parse('2026-09-26 08:00:00')));
    }

    public function test_a_second_sync_with_a_changed_lastmod_marks_the_page_as_edited(): void
    {
        $project = SeoProject::factory()->create(['sitemap_url' => 'https://ganjnameh.example.com/sitemap.xml']);

        // Http::fake() calls don't replace earlier ones for the same URL -
        // they stack, and the first-registered match wins - so getting two
        // different responses from the *same* URL across two syncs needs
        // one sequence, not two separate Http::fake() calls.
        Http::fake([
            'ganjnameh.example.com/sitemap.xml' => Http::sequence()
                ->push(<<<'XML'
                    <?xml version="1.0" encoding="UTF-8"?>
                    <urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
                        <url><loc>https://ganjnameh.example.com/telecabin</loc><lastmod>2026-09-20T10:00:00+00:00</lastmod></url>
                    </urlset>
                    XML, 200)
                ->push(<<<'XML'
                    <?xml version="1.0" encoding="UTF-8"?>
                    <urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
                        <url><loc>https://ganjnameh.example.com/telecabin</loc><lastmod>2026-09-26T09:00:00+00:00</lastmod></url>
                    </urlset>
                    XML, 200),
            'ganjnameh.example.com/telecabin' => Http::response('<html><head><title>x</title></head></html>', 200, ['Content-Type' => 'text/html']),
        ]);

        $service = app(SitemapMonitorService::class);

        Carbon::setTestNow('2026-09-25 08:00:00');
        $service->sync($project);

        Carbon::setTestNow('2026-09-27 08:00:00');
        $service->sync($project);

        $entry = SeoSitemapEntry::where('seo_project_id', $project->id)->first();

        $this->assertTrue($entry->last_changed_at->equalTo(Carbon::parse('2026-09-27 08:00:00')));
        $this->assertFalse($entry->first_seen_at->equalTo($entry->last_changed_at));

        // یک آدرس که هنوز موجود است هرگز دوباره fetch نمی‌شود - فقط برای
        // آدرس‌های تازه‌کشف‌شده عنوان گرفته می‌شود: sync اول دو درخواست
        // زد (sitemap + عنوان صفحه‌ی تازه)، sync دوم فقط یک درخواست
        // (خود sitemap) - جمعاً سه درخواست، نه چهار.
        Http::assertSentCount(3);
    }

    public function test_it_throws_when_the_project_has_no_sitemap_url_registered(): void
    {
        $project = SeoProject::factory()->create(['sitemap_url' => null]);

        $this->expectException(\RuntimeException::class);

        app(SitemapMonitorService::class)->sync($project);
    }

    public function test_a_changed_lastmod_resets_the_page_s_classification(): void
    {
        $project = SeoProject::factory()->create(['sitemap_url' => 'https://ganjnameh.example.com/sitemap.xml']);
        $cluster = SeoTopicCluster::factory()->for($project, 'project')->create();
        $entry = SeoSitemapEntry::factory()->create([
            'seo_project_id' => $project->id,
            'url' => 'https://ganjnameh.example.com/telecabin',
            'url_hash' => sha1('https://ganjnameh.example.com/telecabin'),
            'lastmod' => Carbon::parse('2026-09-20T10:00:00+00:00'),
            'topic_cluster_id' => $cluster->id,
            'classified_at' => now(),
        ]);

        Http::fake([
            'ganjnameh.example.com/sitemap.xml' => Http::response(<<<'XML'
                <?xml version="1.0" encoding="UTF-8"?>
                <urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
                    <url><loc>https://ganjnameh.example.com/telecabin</loc><lastmod>2026-09-26T09:00:00+00:00</lastmod></url>
                </urlset>
                XML, 200),
        ]);

        app(SitemapMonitorService::class)->sync($project);

        $entry->refresh();
        $this->assertNull($entry->topic_cluster_id);
        $this->assertNull($entry->classified_at);
    }

    public function test_coverage_by_cluster_counts_classified_pages_per_cluster(): void
    {
        $project = SeoProject::factory()->create();
        $clusterA = SeoTopicCluster::factory()->for($project, 'project')->create();
        $clusterB = SeoTopicCluster::factory()->for($project, 'project')->create();
        SeoSitemapEntry::factory()->count(2)->create(['seo_project_id' => $project->id, 'topic_cluster_id' => $clusterA->id, 'classified_at' => now()]);
        SeoSitemapEntry::factory()->create(['seo_project_id' => $project->id, 'topic_cluster_id' => $clusterB->id, 'classified_at' => now()]);
        SeoSitemapEntry::factory()->create(['seo_project_id' => $project->id, 'topic_cluster_id' => null, 'classified_at' => now()]);

        $coverage = app(SitemapMonitorService::class)->coverageByCluster($project);

        $this->assertSame(2, $coverage->get($clusterA->id));
        $this->assertSame(1, $coverage->get($clusterB->id));
        $this->assertCount(2, $coverage);
    }
}
