<?php

namespace Tests\Feature\Seo;

use App\Domain\Seo\OwnSitemapTopicMatcher;
use App\Domain\Seo\SitemapMonitorService;
use App\Jobs\SyncSitemapDataJob;
use App\Models\AiProvider;
use App\Models\SeoProject;
use App\Models\SeoSitemapEntry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SyncSitemapDataJobTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_syncs_the_sitemap_and_then_classifies_the_newly_synced_pages(): void
    {
        AiProvider::create([
            'name' => 'Test Provider',
            'base_url' => 'https://api.test-provider.example/v1',
            'api_key' => 'test-key',
            'default_model' => 'gpt-test',
            'is_active' => true,
        ]);

        $project = SeoProject::factory()->create(['sitemap_url' => 'https://ganjnameh.example.com/sitemap.xml']);

        Http::fake([
            'ganjnameh.example.com/sitemap.xml' => Http::response(<<<'XML'
                <?xml version="1.0" encoding="UTF-8"?>
                <urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
                    <url><loc>https://ganjnameh.example.com/eco-tour</loc><lastmod>2026-09-20T10:00:00+00:00</lastmod></url>
                </urlset>
                XML, 200),
            'ganjnameh.example.com/eco-tour' => Http::response('<html><head><title>تور طبیعت‌گردی همدان</title></head></html>', 200, ['Content-Type' => 'text/html']),
            'api.test-provider.example/*' => Http::response([
                'choices' => [['message' => ['content' => json_encode([
                    ['url' => 'https://ganjnameh.example.com/eco-tour', 'topic' => 'تور طبیعت‌گردی'],
                ], JSON_UNESCAPED_UNICODE)]]],
            ]),
        ]);

        (new SyncSitemapDataJob($project->id))->handle(
            app(SitemapMonitorService::class),
            app(OwnSitemapTopicMatcher::class),
        );

        $entry = SeoSitemapEntry::where('seo_project_id', $project->id)->sole();

        $this->assertSame('https://ganjnameh.example.com/eco-tour', $entry->url);
        $this->assertSame('تور طبیعت‌گردی', $entry->topicCluster->name);
        $this->assertNotNull($entry->classified_at);
    }
}
