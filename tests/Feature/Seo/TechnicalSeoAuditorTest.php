<?php

namespace Tests\Feature\Seo;

use App\Domain\Seo\TechnicalSeoAuditor;
use App\Enums\SeoTechnicalAuditStatus;
use App\Enums\SeoTechnicalIssueType;
use App\Models\SeoInternalLink;
use App\Models\SeoProject;
use App\Models\SeoRankTrackerSetting;
use App\Models\SeoSitemapEntry;
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
                '<html><head><title>خانه</title><meta name="description" content="x"><script type="application/ld+json">{"@type":"LocalBusiness","telephone":"+988112345678"}</script></head><body><img src="a.jpg" alt="توضیح"><a href="tel:+988112345678">تماس</a></body></html>',
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
        $this->assertStringContainsString('تنظیم نشده', $audit->performance_error);
    }

    public function test_a_rejected_page_speed_api_key_is_recorded_as_a_readable_error(): void
    {
        // Regression: this used to only go to the log - the admin had no
        // way to tell from the panel why the score never showed up.
        SeoRankTrackerSetting::current()->update(['api_key' => 'bad-key', 'search_engine_id' => 'cx']);

        Http::fake([
            'www.googleapis.com/pagespeedonline/*' => Http::response(['error' => ['message' => 'API key not valid.']], 400),
            'bad-key-site.test/robots.txt' => Http::response('', 200),
            'bad-key-site.test/sitemap.xml' => Http::response('', 200),
            'bad-key-site.test*' => Http::response(
                '<html><head><title>خانه</title><meta name="description" content="x"></head><body>ok</body></html>',
                200,
                ['Content-Type' => 'text/html'],
            ),
        ]);

        $project = SeoProject::factory()->create(['website_url' => 'https://bad-key-site.test']);

        $audit = app(TechnicalSeoAuditor::class)->audit($project);

        $this->assertSame(SeoTechnicalAuditStatus::Completed, $audit->status);
        $this->assertNull($audit->performance_score);
        $this->assertStringContainsString('API key not valid.', $audit->performance_error);
    }

    public function test_it_stores_the_internal_link_graph_deduplicated(): void
    {
        Http::fake([
            'graph-site.test/robots.txt' => Http::response('', 200),
            'graph-site.test/sitemap.xml' => Http::response('', 200),
            'graph-site.test/about' => Http::response(
                '<html><head><title>دربارهٔ ما</title><meta name="description" content="x"></head><body>ok</body></html>',
                200,
                ['Content-Type' => 'text/html'],
            ),
            'graph-site.test*' => Http::response(
                // Two links to /about in the same page (e.g. nav + footer) -
                // must collapse into a single stored edge.
                '<html><head><title>خانه</title><meta name="description" content="x"></head><body><a href="/about">۱</a><a href="/about">۲</a></body></html>',
                200,
                ['Content-Type' => 'text/html'],
            ),
        ]);

        $project = SeoProject::factory()->create(['website_url' => 'https://graph-site.test']);

        $audit = app(TechnicalSeoAuditor::class)->audit($project);

        $this->assertSame(1, SeoInternalLink::where('seo_technical_audit_id', $audit->id)->count());
        $this->assertDatabaseHas(SeoInternalLink::class, [
            'seo_technical_audit_id' => $audit->id,
            'from_url' => 'https://graph-site.test',
            'to_url' => 'https://graph-site.test/about',
        ]);
    }

    public function test_it_flags_a_sitemap_url_the_crawl_never_reached_as_an_orphan_page(): void
    {
        Http::fake([
            'orphan-site.test/robots.txt' => Http::response('', 200),
            'orphan-site.test/sitemap.xml' => Http::response('', 200),
            'orphan-site.test/about' => Http::response(
                '<html><head><title>دربارهٔ ما</title><meta name="description" content="x"></head><body>ok</body></html>',
                200,
                ['Content-Type' => 'text/html'],
            ),
            'orphan-site.test*' => Http::response(
                '<html><head><title>خانه</title><meta name="description" content="x"></head><body><a href="/about">دربارهٔ ما</a></body></html>',
                200,
                ['Content-Type' => 'text/html'],
            ),
        ]);

        $project = SeoProject::factory()->create(['website_url' => 'https://orphan-site.test']);
        SeoSitemapEntry::factory()->create([
            'seo_project_id' => $project->id,
            'url' => 'https://orphan-site.test/secret-page',
        ]);

        $audit = app(TechnicalSeoAuditor::class)->audit($project);

        $issue = $audit->issues()->where('type', SeoTechnicalIssueType::OrphanPage->value)->first();
        $this->assertNotNull($issue);
        $this->assertSame('https://orphan-site.test/secret-page', $issue->url);

        // /about WAS reached via an internal link, so it must never be
        // flagged even though it's also in the sitemap.
        $this->assertFalse(
            $audit->issues()
                ->where('type', SeoTechnicalIssueType::OrphanPage->value)
                ->where('url', 'https://orphan-site.test/about')
                ->exists(),
        );
    }

    public function test_it_never_flags_an_orphan_when_the_page_cap_cut_the_crawl_short(): void
    {
        // A 42-node chain (home -> page1 -> page2 -> ... -> page41) forces
        // the crawler's 40-page cap to cut the crawl off mid-chain, with
        // unvisited pages still left in the queue - proving the crawl did
        // NOT exhaust the site, so nothing should be reported as orphaned.
        $fakes = [
            'chain-site.test/robots.txt' => Http::response('', 200),
            'chain-site.test/sitemap.xml' => Http::response('', 200),
            'chain-site.test' => Http::response(
                '<html><head><title>خانه</title><meta name="description" content="x"></head><body><a href="/page1">بعدی</a></body></html>',
                200,
                ['Content-Type' => 'text/html'],
            ),
        ];

        for ($i = 1; $i <= 41; $i++) {
            $fakes["chain-site.test/page{$i}"] = Http::response(
                '<html><head><title>صفحه '.$i.'</title><meta name="description" content="x"></head><body><a href="/page'.($i + 1).'">بعدی</a></body></html>',
                200,
                ['Content-Type' => 'text/html'],
            );
        }

        Http::fake($fakes);

        $project = SeoProject::factory()->create(['website_url' => 'https://chain-site.test']);
        SeoSitemapEntry::factory()->create([
            'seo_project_id' => $project->id,
            'url' => 'https://chain-site.test/page41',
        ]);

        $audit = app(TechnicalSeoAuditor::class)->audit($project);

        $this->assertSame(40, $audit->pages_scanned);
        $this->assertSame(0, $audit->issues()->where('type', SeoTechnicalIssueType::OrphanPage->value)->count());
    }

    public function test_it_flags_inconsistent_phone_numbers_found_across_different_pages(): void
    {
        Http::fake([
            'phone-site.test/robots.txt' => Http::response('', 200),
            'phone-site.test/sitemap.xml' => Http::response('', 200),
            'phone-site.test/about' => Http::response(
                '<html><head><title>دربارهٔ ما</title><meta name="description" content="x"></head><body><a href="tel:+988187654321">تماس</a></body></html>',
                200,
                ['Content-Type' => 'text/html'],
            ),
            'phone-site.test*' => Http::response(
                '<html><head><title>خانه</title><meta name="description" content="x"></head><body><a href="/about">دربارهٔ ما</a><a href="tel:+988112345678">تماس</a></body></html>',
                200,
                ['Content-Type' => 'text/html'],
            ),
        ]);

        $project = SeoProject::factory()->create(['website_url' => 'https://phone-site.test']);

        $audit = app(TechnicalSeoAuditor::class)->audit($project);

        $issue = $audit->issues()->where('type', SeoTechnicalIssueType::InconsistentPhoneNumber->value)->first();
        $this->assertNotNull($issue);
        $this->assertStringContainsString('+988112345678', $issue->details);
        $this->assertStringContainsString('+988187654321', $issue->details);
    }

    public function test_it_does_not_flag_phone_numbers_when_the_same_number_appears_on_every_page_despite_formatting_differences(): void
    {
        Http::fake([
            'same-phone-site.test/robots.txt' => Http::response('', 200),
            'same-phone-site.test/sitemap.xml' => Http::response('', 200),
            'same-phone-site.test/about' => Http::response(
                '<html><head><title>دربارهٔ ما</title><meta name="description" content="x"></head><body><a href="tel:+98 81 1234 5678">تماس</a></body></html>',
                200,
                ['Content-Type' => 'text/html'],
            ),
            'same-phone-site.test*' => Http::response(
                '<html><head><title>خانه</title><meta name="description" content="x"></head><body><a href="/about">دربارهٔ ما</a><a href="tel:+988112345678">تماس</a></body></html>',
                200,
                ['Content-Type' => 'text/html'],
            ),
        ]);

        $project = SeoProject::factory()->create(['website_url' => 'https://same-phone-site.test']);

        $audit = app(TechnicalSeoAuditor::class)->audit($project);

        $this->assertFalse($audit->issues()->where('type', SeoTechnicalIssueType::InconsistentPhoneNumber->value)->exists());
    }

    public function test_it_flags_the_homepage_as_missing_a_local_business_schema_when_only_a_generic_type_is_declared(): void
    {
        Http::fake([
            'generic-ld-site.test/robots.txt' => Http::response('', 200),
            'generic-ld-site.test/sitemap.xml' => Http::response('', 200),
            'generic-ld-site.test*' => Http::response(
                '<html><head><title>خانه</title><meta name="description" content="x"><script type="application/ld+json">{"@type":"Organization"}</script></head><body>ok</body></html>',
                200,
                ['Content-Type' => 'text/html'],
            ),
        ]);

        $project = SeoProject::factory()->create(['website_url' => 'https://generic-ld-site.test']);

        $audit = app(TechnicalSeoAuditor::class)->audit($project);

        $issue = $audit->issues()->where('type', SeoTechnicalIssueType::MissingLocalBusinessSchema->value)->first();
        $this->assertNotNull($issue);
        $this->assertSame('https://generic-ld-site.test', $issue->url);

        // Generic structured data IS present, so MissingStructuredData
        // (a different, already-existing issue) must not also fire.
        $this->assertFalse($audit->issues()->where('type', SeoTechnicalIssueType::MissingStructuredData->value)->exists());
    }

    public function test_it_does_not_flag_missing_local_business_schema_when_the_homepage_declares_a_recognized_local_business_type(): void
    {
        Http::fake([
            'lodging-ld-site.test/robots.txt' => Http::response('', 200),
            'lodging-ld-site.test/sitemap.xml' => Http::response('', 200),
            'lodging-ld-site.test*' => Http::response(
                '<html><head><title>خانه</title><meta name="description" content="x"><script type="application/ld+json">{"@type":"LodgingBusiness"}</script></head><body>ok</body></html>',
                200,
                ['Content-Type' => 'text/html'],
            ),
        ]);

        $project = SeoProject::factory()->create(['website_url' => 'https://lodging-ld-site.test']);

        $audit = app(TechnicalSeoAuditor::class)->audit($project);

        $this->assertFalse($audit->issues()->where('type', SeoTechnicalIssueType::MissingLocalBusinessSchema->value)->exists());
    }

    public function test_it_does_not_double_flag_the_homepage_when_it_has_no_structured_data_at_all(): void
    {
        Http::fake([
            'no-ld-homepage.test/robots.txt' => Http::response('', 200),
            'no-ld-homepage.test/sitemap.xml' => Http::response('', 200),
            'no-ld-homepage.test*' => Http::response(
                '<html><head><title>خانه</title><meta name="description" content="x"></head><body>ok</body></html>',
                200,
                ['Content-Type' => 'text/html'],
            ),
        ]);

        $project = SeoProject::factory()->create(['website_url' => 'https://no-ld-homepage.test']);

        $audit = app(TechnicalSeoAuditor::class)->audit($project);

        // Already covered by MissingStructuredData - flagging both for
        // the exact same missing data would just be noise.
        $this->assertTrue($audit->issues()->where('type', SeoTechnicalIssueType::MissingStructuredData->value)->exists());
        $this->assertFalse($audit->issues()->where('type', SeoTechnicalIssueType::MissingLocalBusinessSchema->value)->exists());
    }

    public function test_it_only_checks_local_business_schema_on_the_homepage_not_every_page(): void
    {
        Http::fake([
            'subpage-ld-site.test/robots.txt' => Http::response('', 200),
            'subpage-ld-site.test/sitemap.xml' => Http::response('', 200),
            'subpage-ld-site.test/about' => Http::response(
                '<html><head><title>دربارهٔ ما</title><meta name="description" content="x"><script type="application/ld+json">{"@type":"Article"}</script></head><body>ok</body></html>',
                200,
                ['Content-Type' => 'text/html'],
            ),
            'subpage-ld-site.test*' => Http::response(
                '<html><head><title>خانه</title><meta name="description" content="x"><script type="application/ld+json">{"@type":"LocalBusiness"}</script></head><body><a href="/about">دربارهٔ ما</a></body></html>',
                200,
                ['Content-Type' => 'text/html'],
            ),
        ]);

        $project = SeoProject::factory()->create(['website_url' => 'https://subpage-ld-site.test']);

        $audit = app(TechnicalSeoAuditor::class)->audit($project);

        // /about declares a non-local "Article" type, but since it is not
        // the homepage this must never be flagged.
        $this->assertFalse($audit->issues()->where('type', SeoTechnicalIssueType::MissingLocalBusinessSchema->value)->exists());
    }
}
