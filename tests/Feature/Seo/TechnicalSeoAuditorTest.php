<?php

namespace Tests\Feature\Seo;

use App\Domain\Seo\TechnicalSeoAuditor;
use App\Enums\SeoTechnicalAuditStatus;
use App\Enums\SeoTechnicalIssueType;
use App\Models\SeoProject;
use App\Models\SeoRankTrackerSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class TechnicalSeoAuditorTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_crawls_same_host_links_and_flags_missing_title_meta_and_alt(): void
    {
        Http::fake([
            'audit-site.test/robots.txt' => Http::response('User-agent: *', 200),
            'audit-site.test/sitemap.xml' => Http::response('<xml></xml>', 200),
            'audit-site.test/about' => Http::response(
                '<html><head><title>دربارهٔ ما</title><meta name="description" content="توضیحات"></head><body>ok</body></html>',
                200,
                ['Content-Type' => 'text/html'],
            ),
            'audit-site.test*' => Http::response(
                '<html><head></head><body><img src="a.jpg"><a href="/about">دربارهٔ ما</a></body></html>',
                200,
                ['Content-Type' => 'text/html'],
            ),
        ]);

        $project = SeoProject::factory()->create(['website_url' => 'https://audit-site.test']);

        $audit = app(TechnicalSeoAuditor::class)->audit($project);

        $this->assertSame(SeoTechnicalAuditStatus::Completed, $audit->status);
        $this->assertSame(2, $audit->pages_scanned);
        $this->assertNotNull($audit->score);

        $this->assertTrue($audit->issues()->where('type', SeoTechnicalIssueType::MissingTitle->value)->exists());
        $this->assertTrue($audit->issues()->where('type', SeoTechnicalIssueType::MissingMetaDescription->value)->exists());
        $this->assertTrue($audit->issues()->where('type', SeoTechnicalIssueType::MissingAlt->value)->exists());

        // /about has both a title and a meta description, so it must not
        // be the page a MissingTitle/MissingMetaDescription issue points at.
        $this->assertFalse(
            $audit->issues()
                ->whereIn('type', [SeoTechnicalIssueType::MissingTitle->value, SeoTechnicalIssueType::MissingMetaDescription->value])
                ->where('url', 'https://audit-site.test/about')
                ->exists(),
        );
    }

    public function test_it_flags_a_broken_link_without_crawling_it_further(): void
    {
        Http::fake([
            'broken-site.test/robots.txt' => Http::response('', 200),
            'broken-site.test/sitemap.xml' => Http::response('', 200),
            'broken-site.test/missing' => Http::response('', 404),
            'broken-site.test*' => Http::response(
                '<html><head><title>خانه</title><meta name="description" content="x"></head><body><a href="/missing">لینک خراب</a></body></html>',
                200,
                ['Content-Type' => 'text/html'],
            ),
        ]);

        $project = SeoProject::factory()->create(['website_url' => 'https://broken-site.test']);

        $audit = app(TechnicalSeoAuditor::class)->audit($project);

        $issue = $audit->issues()->where('type', SeoTechnicalIssueType::BrokenLink->value)->first();
        $this->assertNotNull($issue);
        $this->assertSame('https://broken-site.test/missing', $issue->url);
    }

    public function test_it_flags_missing_robots_and_sitemap(): void
    {
        Http::fake([
            'no-robots.test/robots.txt' => Http::response('', 404),
            'no-robots.test/sitemap.xml' => Http::response('', 404),
            'no-robots.test*' => Http::response(
                '<html><head><title>خانه</title><meta name="description" content="x"></head><body>ok</body></html>',
                200,
                ['Content-Type' => 'text/html'],
            ),
        ]);

        $project = SeoProject::factory()->create(['website_url' => 'https://no-robots.test']);

        $audit = app(TechnicalSeoAuditor::class)->audit($project);

        $this->assertTrue($audit->issues()->where('type', SeoTechnicalIssueType::MissingRobots->value)->exists());
        $this->assertTrue($audit->issues()->where('type', SeoTechnicalIssueType::MissingSitemap->value)->exists());
    }

    public function test_a_clean_site_scores_a_perfect_one_hundred(): void
    {
        Http::fake([
            'clean-site.test/robots.txt' => Http::response('User-agent: *', 200),
            'clean-site.test/sitemap.xml' => Http::response('<xml></xml>', 200),
            'clean-site.test*' => Http::response(
                '<html><head><title>خانه</title><meta name="description" content="x"><script type="application/ld+json">{"@type":"Organization"}</script></head><body><img src="a.jpg" alt="توضیح"></body></html>',
                200,
                ['Content-Type' => 'text/html'],
            ),
        ]);

        $project = SeoProject::factory()->create(['website_url' => 'https://clean-site.test']);

        $audit = app(TechnicalSeoAuditor::class)->audit($project);

        $this->assertSame(0, $audit->issues()->count());
        $this->assertSame(100, $audit->score);
    }

    public function test_an_unreachable_site_marks_the_audit_as_failed_or_records_a_broken_link_issue(): void
    {
        Http::fake([
            '*' => Http::response('', 500),
        ]);

        $project = SeoProject::factory()->create(['website_url' => 'https://down-site.test']);

        $audit = app(TechnicalSeoAuditor::class)->audit($project);

        // The homepage itself failing is still a completed audit (one
        // broken-link issue recorded), not an exception - the auditor
        // never crashes the whole run over one bad response.
        $this->assertSame(SeoTechnicalAuditStatus::Completed, $audit->status);
        $this->assertTrue($audit->issues()->where('type', SeoTechnicalIssueType::BrokenLink->value)->exists());
    }

    public function test_it_flags_a_page_with_no_json_ld_structured_data(): void
    {
        Http::fake([
            'no-ld-site.test/robots.txt' => Http::response('', 200),
            'no-ld-site.test/sitemap.xml' => Http::response('', 200),
            'no-ld-site.test*' => Http::response(
                '<html><head><title>خانه</title><meta name="description" content="x"></head><body>ok</body></html>',
                200,
                ['Content-Type' => 'text/html'],
            ),
        ]);

        $project = SeoProject::factory()->create(['website_url' => 'https://no-ld-site.test']);

        $audit = app(TechnicalSeoAuditor::class)->audit($project);

        $this->assertTrue($audit->issues()->where('type', SeoTechnicalIssueType::MissingStructuredData->value)->exists());
    }

    public function test_it_does_not_flag_a_page_that_has_json_ld_structured_data(): void
    {
        Http::fake([
            'has-ld-site.test/robots.txt' => Http::response('', 200),
            'has-ld-site.test/sitemap.xml' => Http::response('', 200),
            'has-ld-site.test*' => Http::response(
                '<html><head><title>خانه</title><meta name="description" content="x"><script type="application/ld+json">{"@type":"Organization"}</script></head><body>ok</body></html>',
                200,
                ['Content-Type' => 'text/html'],
            ),
        ]);

        $project = SeoProject::factory()->create(['website_url' => 'https://has-ld-site.test']);

        $audit = app(TechnicalSeoAuditor::class)->audit($project);

        $this->assertFalse($audit->issues()->where('type', SeoTechnicalIssueType::MissingStructuredData->value)->exists());
    }

    public function test_it_records_performance_score_and_core_web_vitals_when_page_speed_is_configured(): void
    {
        SeoRankTrackerSetting::current()->update(['api_key' => 'test-key', 'search_engine_id' => 'cx']);

        Http::fake([
            'www.googleapis.com/pagespeedonline/*' => Http::response([
                'lighthouseResult' => [
                    'categories' => ['performance' => ['score' => 0.62]],
                    'audits' => [
                        'largest-contentful-paint' => ['numericValue' => 3100.4],
                        'cumulative-layout-shift' => ['numericValue' => 0.08],
                    ],
                ],
            ]),
            'speed-site.test/robots.txt' => Http::response('', 200),
            'speed-site.test/sitemap.xml' => Http::response('', 200),
            'speed-site.test*' => Http::response(
                '<html><head><title>خانه</title><meta name="description" content="x"></head><body>ok</body></html>',
                200,
                ['Content-Type' => 'text/html'],
            ),
        ]);

        $project = SeoProject::factory()->create(['website_url' => 'https://speed-site.test']);

        $audit = app(TechnicalSeoAuditor::class)->audit($project);

        $this->assertSame(62, $audit->performance_score);
        $this->assertSame(3100.4, $audit->core_web_vitals['lcp_ms']);
        $this->assertSame(0.08, $audit->core_web_vitals['cls']);
    }

    public function test_the_audit_still_completes_when_page_speed_is_not_configured(): void
    {
        Http::fake([
            'no-speed-site.test/robots.txt' => Http::response('', 200),
            'no-speed-site.test/sitemap.xml' => Http::response('', 200),
            'no-speed-site.test*' => Http::response(
                '<html><head><title>خانه</title><meta name="description" content="x"></head><body>ok</body></html>',
                200,
                ['Content-Type' => 'text/html'],
            ),
        ]);

        $project = SeoProject::factory()->create(['website_url' => 'https://no-speed-site.test']);

        $audit = app(TechnicalSeoAuditor::class)->audit($project);

        $this->assertSame(SeoTechnicalAuditStatus::Completed, $audit->status);
        $this->assertNull($audit->performance_score);
        $this->assertNull($audit->core_web_vitals);
    }
}
