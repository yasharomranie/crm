<?php

namespace Tests\Feature\Seo;

use App\Domain\Seo\CompetitorContentGapService;
use App\Domain\Seo\CompetitorSitemapCrawler;
use App\Domain\Seo\CompetitorTopicMatcher;
use App\Domain\Seo\SeoAlertService;
use App\Jobs\AnalyzeCompetitorContentGapsJob;
use App\Models\AiProvider;
use App\Models\SeoAlertSetting;
use App\Models\SeoCompetitorSitemapEntry;
use App\Models\SeoKeywordSuggestion;
use App\Models\SeoProject;
use App\Models\SeoProjectCompetitor;
use App\Models\User;
use App\Notifications\SeoCompetitorActivityNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class AnalyzeCompetitorContentGapsJobTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_crawls_classifies_and_detects_gaps_end_to_end(): void
    {
        AiProvider::create([
            'name' => 'Test Provider',
            'base_url' => 'https://api.test-provider.example/v1',
            'api_key' => 'test-key',
            'default_model' => 'gpt-test',
            'is_active' => true,
        ]);

        $project = SeoProject::factory()->create();
        $competitorA = SeoProjectCompetitor::factory()->for($project, 'project')->create([
            'domain' => 'competitor-a.example.com',
            'sitemap_url' => 'https://competitor-a.example.com/sitemap.xml',
        ]);
        $competitorB = SeoProjectCompetitor::factory()->for($project, 'project')->create([
            'domain' => 'competitor-b.example.com',
            'sitemap_url' => 'https://competitor-b.example.com/sitemap.xml',
        ]);

        Http::fake([
            'competitor-a.example.com/sitemap.xml' => Http::response(<<<'XML'
                <?xml version="1.0" encoding="UTF-8"?>
                <urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
                    <url><loc>https://competitor-a.example.com/eco-tour</loc><lastmod>2026-09-20T10:00:00+00:00</lastmod></url>
                </urlset>
                XML, 200),
            'competitor-a.example.com/eco-tour' => Http::response('<html><head><title>تور طبیعت‌گردی همدان</title></head></html>', 200, ['Content-Type' => 'text/html']),
            'competitor-b.example.com/sitemap.xml' => Http::response(<<<'XML'
                <?xml version="1.0" encoding="UTF-8"?>
                <urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
                    <url><loc>https://competitor-b.example.com/nature-tour</loc><lastmod>2026-09-20T10:00:00+00:00</lastmod></url>
                </urlset>
                XML, 200),
            'competitor-b.example.com/nature-tour' => Http::response('<html><head><title>تور طبیعت‌گردی همدان - رزرو</title></head></html>', 200, ['Content-Type' => 'text/html']),
            'api.test-provider.example/*' => Http::response([
                'choices' => [['message' => ['content' => json_encode([
                    ['url' => 'https://competitor-a.example.com/eco-tour', 'topic' => 'تور طبیعت‌گردی'],
                    ['url' => 'https://competitor-b.example.com/nature-tour', 'topic' => 'تور طبیعت‌گردی'],
                ], JSON_UNESCAPED_UNICODE)]]],
            ]),
        ]);

        (new AnalyzeCompetitorContentGapsJob($project->id))->handle(
            app(CompetitorSitemapCrawler::class),
            app(CompetitorTopicMatcher::class),
            app(CompetitorContentGapService::class),
            app(SeoAlertService::class),
        );

        $this->assertSame(2, SeoCompetitorSitemapEntry::count());
        $this->assertSame(1, SeoKeywordSuggestion::count());
        $this->assertSame('تور طبیعت‌گردی', SeoKeywordSuggestion::sole()->keyword);
    }

    public function test_one_unreachable_competitor_never_blocks_the_others(): void
    {
        AiProvider::create([
            'name' => 'Test Provider',
            'base_url' => 'https://api.test-provider.example/v1',
            'api_key' => 'test-key',
            'default_model' => 'gpt-test',
            'is_active' => true,
        ]);

        $project = SeoProject::factory()->create();
        SeoProjectCompetitor::factory()->for($project, 'project')->create([
            'domain' => 'dead-competitor.example.com',
            'sitemap_url' => null,
        ]);
        SeoProjectCompetitor::factory()->for($project, 'project')->create([
            'domain' => 'competitor-b.example.com',
            'sitemap_url' => 'https://competitor-b.example.com/sitemap.xml',
        ]);

        Http::fake([
            'dead-competitor.example.com/*' => Http::response('Not Found', 404),
            'competitor-b.example.com/sitemap.xml' => Http::response(<<<'XML'
                <?xml version="1.0" encoding="UTF-8"?>
                <urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
                    <url><loc>https://competitor-b.example.com/nature-tour</loc><lastmod>2026-09-20T10:00:00+00:00</lastmod></url>
                </urlset>
                XML, 200),
            'competitor-b.example.com/nature-tour' => Http::response('<html><head><title>تور طبیعت‌گردی</title></head></html>', 200, ['Content-Type' => 'text/html']),
            'api.test-provider.example/*' => Http::response([
                'choices' => [['message' => ['content' => json_encode([
                    ['url' => 'https://competitor-b.example.com/nature-tour', 'topic' => null],
                ], JSON_UNESCAPED_UNICODE)]]],
            ]),
        ]);

        (new AnalyzeCompetitorContentGapsJob($project->id))->handle(
            app(CompetitorSitemapCrawler::class),
            app(CompetitorTopicMatcher::class),
            app(CompetitorContentGapService::class),
            app(SeoAlertService::class),
        );

        $this->assertSame(1, SeoCompetitorSitemapEntry::count());
    }

    public function test_it_alerts_when_a_competitor_publishes_new_pages(): void
    {
        AiProvider::create([
            'name' => 'Test Provider',
            'base_url' => 'https://api.test-provider.example/v1',
            'api_key' => 'test-key',
            'default_model' => 'gpt-test',
            'is_active' => true,
        ]);
        $user = User::factory()->create();
        SeoAlertSetting::current()->update(['is_enabled' => true, 'recipient_user_ids' => [$user->id]]);
        Notification::fake();

        $project = SeoProject::factory()->create();
        $competitor = SeoProjectCompetitor::factory()->for($project, 'project')->create([
            'domain' => 'competitor.example.com',
            'sitemap_url' => 'https://competitor.example.com/sitemap.xml',
        ]);

        Http::fake([
            'competitor.example.com/sitemap.xml' => Http::response(<<<'XML'
                <?xml version="1.0" encoding="UTF-8"?>
                <urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
                    <url><loc>https://competitor.example.com/new-page</loc><lastmod>2026-09-20T10:00:00+00:00</lastmod></url>
                </urlset>
                XML, 200),
            'competitor.example.com/new-page' => Http::response('<html><head><title>صفحه جدید</title></head></html>', 200, ['Content-Type' => 'text/html']),
            'api.test-provider.example/*' => Http::response([
                'choices' => [['message' => ['content' => json_encode([
                    ['url' => 'https://competitor.example.com/new-page', 'topic' => null],
                ], JSON_UNESCAPED_UNICODE)]]],
            ]),
        ]);

        (new AnalyzeCompetitorContentGapsJob($project->id))->handle(
            app(CompetitorSitemapCrawler::class),
            app(CompetitorTopicMatcher::class),
            app(CompetitorContentGapService::class),
            app(SeoAlertService::class),
        );

        Notification::assertSentTo($user, SeoCompetitorActivityNotification::class);
    }
}
