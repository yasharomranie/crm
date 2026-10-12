<?php

namespace Tests\Feature\Seo;

use App\Domain\Seo\CompetitorSitemapResolver;
use App\Models\SeoProjectCompetitor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class CompetitorSitemapResolverTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_explicit_sitemap_url_always_wins_without_any_request(): void
    {
        $competitor = SeoProjectCompetitor::factory()->create([
            'domain' => 'competitor.example.com',
            'sitemap_url' => 'https://competitor.example.com/custom-sitemap.xml',
        ]);

        $resolved = (new CompetitorSitemapResolver)->resolve($competitor);

        $this->assertSame('https://competitor.example.com/custom-sitemap.xml', $resolved);
        Http::assertNothingSent();
    }

    public function test_it_finds_the_sitemap_at_the_first_common_path_that_responds_with_xml(): void
    {
        $competitor = SeoProjectCompetitor::factory()->create(['domain' => 'competitor.example.com', 'sitemap_url' => null]);

        Http::fake([
            'competitor.example.com/sitemap.xml' => Http::response('<?xml version="1.0"?><urlset></urlset>', 200, ['Content-Type' => 'application/xml']),
        ]);

        $resolved = (new CompetitorSitemapResolver)->resolve($competitor);

        $this->assertSame('https://competitor.example.com/sitemap.xml', $resolved);
    }

    public function test_it_falls_back_to_the_next_common_path_when_the_first_is_missing(): void
    {
        $competitor = SeoProjectCompetitor::factory()->create(['domain' => 'competitor.example.com', 'sitemap_url' => null]);

        Http::fake([
            'competitor.example.com/sitemap.xml' => Http::response('Not Found', 404),
            'competitor.example.com/sitemap_index.xml' => Http::response('<?xml version="1.0"?><sitemapindex></sitemapindex>', 200, ['Content-Type' => 'application/xml']),
        ]);

        $resolved = (new CompetitorSitemapResolver)->resolve($competitor);

        $this->assertSame('https://competitor.example.com/sitemap_index.xml', $resolved);
    }

    public function test_it_falls_back_to_robots_txt_when_no_common_path_works(): void
    {
        $competitor = SeoProjectCompetitor::factory()->create(['domain' => 'competitor.example.com', 'sitemap_url' => null]);

        Http::fake([
            'competitor.example.com/sitemap.xml' => Http::response('Not Found', 404),
            'competitor.example.com/sitemap_index.xml' => Http::response('Not Found', 404),
            'competitor.example.com/sitemap-index.xml' => Http::response('Not Found', 404),
            'competitor.example.com/robots.txt' => Http::response("User-agent: *\nDisallow: /admin\nSitemap: https://competitor.example.com/unusual-path/map.xml\n"),
        ]);

        $resolved = (new CompetitorSitemapResolver)->resolve($competitor);

        $this->assertSame('https://competitor.example.com/unusual-path/map.xml', $resolved);
    }

    public function test_it_returns_null_when_nothing_is_found(): void
    {
        $competitor = SeoProjectCompetitor::factory()->create(['domain' => 'competitor.example.com', 'sitemap_url' => null]);

        Http::fake(fn () => Http::response('Not Found', 404));

        $this->assertNull((new CompetitorSitemapResolver)->resolve($competitor));
    }

    public function test_a_domain_without_a_scheme_is_normalized_to_https(): void
    {
        $competitor = SeoProjectCompetitor::factory()->create(['domain' => 'competitor.example.com', 'sitemap_url' => null]);

        Http::fake([
            'competitor.example.com/sitemap.xml' => Http::response('<?xml version="1.0"?><urlset></urlset>', 200, ['Content-Type' => 'application/xml']),
        ]);

        $resolved = (new CompetitorSitemapResolver)->resolve($competitor);

        $this->assertStringStartsWith('https://', $resolved);
    }
}
