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
        // Enough real body text to clear the thin-content check - the
        // other fixtures in this file stay short on purpose (they're
        // only testing one specific issue each), but a page claiming to
        // be clean under every check needs genuine content too.
        $longText = str_repeat('این متن نمونه برای آزمایش محتوای کافی صفحه است و هیچ مشکلی ندارد. ', 15);

        Http::fake([
            'clean-site.test/robots.txt' => Http::response('User-agent: *', 200),
            'clean-site.test/sitemap.xml' => Http::response('<xml></xml>', 200),
            'clean-site.test*' => Http::response(
                '<html><head><title>خانه</title><meta name="description" content="x">'
                .'<meta name="viewport" content="width=device-width, initial-scale=1">'
                .'<link rel="canonical" href="https://clean-site.test">'
                .'<meta property="og:title" content="خانه"><meta property="og:image" content="https://clean-site.test/og.jpg">'
                .'<script type="application/ld+json">{"@type":"LocalBusiness","telephone":"+988112345678"}</script></head>'
                .'<body><h1>خانه</h1><img src="a.jpg" alt="توضیح" width="100" height="100"><a href="tel:+988112345678">تماس</a><p>'.$longText.'</p></body></html>',
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
        // An 82-node chain (home -> page1 -> page2 -> ... -> page81) forces
        // the crawler's 80-page cap to cut the crawl off mid-chain, with
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

        for ($i = 1; $i <= 81; $i++) {
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
            'url' => 'https://chain-site.test/page81',
        ]);

        $audit = app(TechnicalSeoAuditor::class)->audit($project);

        $this->assertSame(80, $audit->pages_scanned);
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

    public function test_it_flags_a_missing_and_a_multiple_h1(): void
    {
        Http::fake([
            'h1-site.test/robots.txt' => Http::response('', 200),
            'h1-site.test/sitemap.xml' => Http::response('', 200),
            'h1-site.test/no-h1' => Http::response(
                '<html><head><title>بدون H1</title><meta name="description" content="x"></head><body>ok</body></html>',
                200,
                ['Content-Type' => 'text/html'],
            ),
            'h1-site.test*' => Http::response(
                '<html><head><title>خانه</title><meta name="description" content="x"></head><body><h1>یک</h1><h1>دو</h1><a href="/no-h1">لینک</a></body></html>',
                200,
                ['Content-Type' => 'text/html'],
            ),
        ]);

        $project = SeoProject::factory()->create(['website_url' => 'https://h1-site.test']);

        $audit = app(TechnicalSeoAuditor::class)->audit($project);

        $multiple = $audit->issues()->where('type', SeoTechnicalIssueType::MultipleH1->value)->first();
        $this->assertNotNull($multiple);
        $this->assertSame('https://h1-site.test', $multiple->url);

        $missing = $audit->issues()->where('type', SeoTechnicalIssueType::MissingH1->value)->first();
        $this->assertNotNull($missing);
        $this->assertSame('https://h1-site.test/no-h1', $missing->url);
    }

    public function test_it_flags_a_duplicate_h1_reused_across_pages(): void
    {
        Http::fake([
            'dup-h1-site.test/robots.txt' => Http::response('', 200),
            'dup-h1-site.test/sitemap.xml' => Http::response('', 200),
            'dup-h1-site.test/about' => Http::response(
                '<html><head><title>دربارهٔ ما</title><meta name="description" content="x"></head><body><h1>خانه</h1></body></html>',
                200,
                ['Content-Type' => 'text/html'],
            ),
            'dup-h1-site.test*' => Http::response(
                '<html><head><title>خانه</title><meta name="description" content="x"></head><body><h1>خانه</h1><a href="/about">دربارهٔ ما</a></body></html>',
                200,
                ['Content-Type' => 'text/html'],
            ),
        ]);

        $project = SeoProject::factory()->create(['website_url' => 'https://dup-h1-site.test']);

        $audit = app(TechnicalSeoAuditor::class)->audit($project);

        $issue = $audit->issues()->where('type', SeoTechnicalIssueType::DuplicateH1->value)->first();
        $this->assertNotNull($issue);
        $this->assertSame('https://dup-h1-site.test/about', $issue->url);
    }

    public function test_it_flags_a_page_missing_a_canonical_tag(): void
    {
        Http::fake([
            'no-canonical-site.test/robots.txt' => Http::response('', 200),
            'no-canonical-site.test/sitemap.xml' => Http::response('', 200),
            'no-canonical-site.test*' => Http::response(
                '<html><head><title>خانه</title><meta name="description" content="x"></head><body>ok</body></html>',
                200,
                ['Content-Type' => 'text/html'],
            ),
        ]);

        $project = SeoProject::factory()->create(['website_url' => 'https://no-canonical-site.test']);

        $audit = app(TechnicalSeoAuditor::class)->audit($project);

        $this->assertTrue($audit->issues()->where('type', SeoTechnicalIssueType::MissingCanonical->value)->exists());
    }

    public function test_it_does_not_flag_a_page_that_has_a_canonical_tag(): void
    {
        Http::fake([
            'canonical-site.test/robots.txt' => Http::response('', 200),
            'canonical-site.test/sitemap.xml' => Http::response('', 200),
            'canonical-site.test*' => Http::response(
                '<html><head><title>خانه</title><meta name="description" content="x"><link rel="canonical" href="https://canonical-site.test"></head><body>ok</body></html>',
                200,
                ['Content-Type' => 'text/html'],
            ),
        ]);

        $project = SeoProject::factory()->create(['website_url' => 'https://canonical-site.test']);

        $audit = app(TechnicalSeoAuditor::class)->audit($project);

        $this->assertFalse($audit->issues()->where('type', SeoTechnicalIssueType::MissingCanonical->value)->exists());
    }

    public function test_it_flags_a_page_missing_a_viewport_meta_tag(): void
    {
        Http::fake([
            'no-viewport-site.test/robots.txt' => Http::response('', 200),
            'no-viewport-site.test/sitemap.xml' => Http::response('', 200),
            'no-viewport-site.test*' => Http::response(
                '<html><head><title>خانه</title><meta name="description" content="x"></head><body>ok</body></html>',
                200,
                ['Content-Type' => 'text/html'],
            ),
        ]);

        $project = SeoProject::factory()->create(['website_url' => 'https://no-viewport-site.test']);

        $audit = app(TechnicalSeoAuditor::class)->audit($project);

        $this->assertTrue($audit->issues()->where('type', SeoTechnicalIssueType::MissingViewport->value)->exists());
    }

    public function test_it_does_not_flag_a_page_that_has_a_viewport_meta_tag(): void
    {
        Http::fake([
            'viewport-site.test/robots.txt' => Http::response('', 200),
            'viewport-site.test/sitemap.xml' => Http::response('', 200),
            'viewport-site.test*' => Http::response(
                '<html><head><title>خانه</title><meta name="description" content="x"><meta name="viewport" content="width=device-width, initial-scale=1"></head><body>ok</body></html>',
                200,
                ['Content-Type' => 'text/html'],
            ),
        ]);

        $project = SeoProject::factory()->create(['website_url' => 'https://viewport-site.test']);

        $audit = app(TechnicalSeoAuditor::class)->audit($project);

        $this->assertFalse($audit->issues()->where('type', SeoTechnicalIssueType::MissingViewport->value)->exists());
    }

    public function test_it_flags_a_page_missing_open_graph_tags(): void
    {
        Http::fake([
            'no-og-site.test/robots.txt' => Http::response('', 200),
            'no-og-site.test/sitemap.xml' => Http::response('', 200),
            'no-og-site.test*' => Http::response(
                '<html><head><title>خانه</title><meta name="description" content="x"></head><body>ok</body></html>',
                200,
                ['Content-Type' => 'text/html'],
            ),
        ]);

        $project = SeoProject::factory()->create(['website_url' => 'https://no-og-site.test']);

        $audit = app(TechnicalSeoAuditor::class)->audit($project);

        $issue = $audit->issues()->where('type', SeoTechnicalIssueType::MissingOpenGraph->value)->first();
        $this->assertNotNull($issue);
        $this->assertStringContainsString('og:title', $issue->details);
        $this->assertStringContainsString('og:image', $issue->details);
    }

    /**
     * This is exactly why the check exists for this specific business:
     * customers share villa/attraction links in WhatsApp, where a missing
     * og:image produces an empty or ugly link preview.
     */
    public function test_it_does_not_flag_a_page_that_has_both_og_title_and_og_image(): void
    {
        Http::fake([
            'has-og-site.test/robots.txt' => Http::response('', 200),
            'has-og-site.test/sitemap.xml' => Http::response('', 200),
            'has-og-site.test*' => Http::response(
                '<html><head><title>خانه</title><meta name="description" content="x"><meta property="og:title" content="خانه"><meta property="og:image" content="https://has-og-site.test/og.jpg"></head><body>ok</body></html>',
                200,
                ['Content-Type' => 'text/html'],
            ),
        ]);

        $project = SeoProject::factory()->create(['website_url' => 'https://has-og-site.test']);

        $audit = app(TechnicalSeoAuditor::class)->audit($project);

        $this->assertFalse($audit->issues()->where('type', SeoTechnicalIssueType::MissingOpenGraph->value)->exists());
    }

    public function test_it_flags_a_long_redirect_chain(): void
    {
        Http::fake([
            'redirect-site.test/robots.txt' => Http::response('', 200),
            'redirect-site.test/sitemap.xml' => Http::response('', 200),
            'redirect-site.test' => Http::response('', 301, ['Location' => 'https://redirect-site.test/step1']),
            'redirect-site.test/step1' => Http::response('', 301, ['Location' => 'https://redirect-site.test/step2']),
            'redirect-site.test/step2' => Http::response('', 301, ['Location' => 'https://redirect-site.test/step3']),
            'redirect-site.test/step3' => Http::response(
                '<html><head><title>خانه</title><meta name="description" content="x"></head><body>ok</body></html>',
                200,
                ['Content-Type' => 'text/html'],
            ),
        ]);

        $project = SeoProject::factory()->create(['website_url' => 'https://redirect-site.test']);

        $audit = app(TechnicalSeoAuditor::class)->audit($project);

        $issue = $audit->issues()->where('type', SeoTechnicalIssueType::RedirectChain->value)->first();
        $this->assertNotNull($issue);
        $this->assertSame('https://redirect-site.test', $issue->url);
        $this->assertStringContainsString('step3', $issue->details);
    }

    public function test_a_single_redirect_hop_is_not_flagged_as_a_chain(): void
    {
        Http::fake([
            'short-redirect-site.test/robots.txt' => Http::response('', 200),
            'short-redirect-site.test/sitemap.xml' => Http::response('', 200),
            'short-redirect-site.test' => Http::response('', 301, ['Location' => 'https://short-redirect-site.test/final']),
            'short-redirect-site.test/final' => Http::response(
                '<html><head><title>خانه</title><meta name="description" content="x"></head><body>ok</body></html>',
                200,
                ['Content-Type' => 'text/html'],
            ),
        ]);

        $project = SeoProject::factory()->create(['website_url' => 'https://short-redirect-site.test']);

        $audit = app(TechnicalSeoAuditor::class)->audit($project);

        $this->assertSame(0, $audit->issues()->where('type', SeoTechnicalIssueType::RedirectChain->value)->count());
    }

    public function test_it_flags_an_image_with_no_explicit_dimensions(): void
    {
        Http::fake([
            'no-dims-site.test/robots.txt' => Http::response('', 200),
            'no-dims-site.test/sitemap.xml' => Http::response('', 200),
            'no-dims-site.test*' => Http::response(
                '<html><head><title>خانه</title><meta name="description" content="x"></head><body><img src="a.jpg" alt="توضیح"></body></html>',
                200,
                ['Content-Type' => 'text/html'],
            ),
        ]);

        $project = SeoProject::factory()->create(['website_url' => 'https://no-dims-site.test']);

        $audit = app(TechnicalSeoAuditor::class)->audit($project);

        $this->assertTrue($audit->issues()->where('type', SeoTechnicalIssueType::ImageMissingDimensions->value)->exists());
    }

    public function test_it_does_not_flag_an_image_that_has_explicit_dimensions(): void
    {
        Http::fake([
            'has-dims-site.test/robots.txt' => Http::response('', 200),
            'has-dims-site.test/sitemap.xml' => Http::response('', 200),
            'has-dims-site.test*' => Http::response(
                '<html><head><title>خانه</title><meta name="description" content="x"></head><body><img src="a.jpg" alt="توضیح" width="200" height="100"></body></html>',
                200,
                ['Content-Type' => 'text/html'],
            ),
        ]);

        $project = SeoProject::factory()->create(['website_url' => 'https://has-dims-site.test']);

        $audit = app(TechnicalSeoAuditor::class)->audit($project);

        $this->assertFalse($audit->issues()->where('type', SeoTechnicalIssueType::ImageMissingDimensions->value)->exists());
    }

    public function test_it_flags_a_page_with_too_little_body_text(): void
    {
        Http::fake([
            'thin-site.test/robots.txt' => Http::response('', 200),
            'thin-site.test/sitemap.xml' => Http::response('', 200),
            'thin-site.test*' => Http::response(
                '<html><head><title>خانه</title><meta name="description" content="x"></head><body><p>چند کلمه کوتاه.</p></body></html>',
                200,
                ['Content-Type' => 'text/html'],
            ),
        ]);

        $project = SeoProject::factory()->create(['website_url' => 'https://thin-site.test']);

        $audit = app(TechnicalSeoAuditor::class)->audit($project);

        $this->assertTrue($audit->issues()->where('type', SeoTechnicalIssueType::ThinContent->value)->exists());
    }

    public function test_it_does_not_flag_a_page_with_enough_body_text(): void
    {
        $longText = str_repeat('این متن نمونه برای آزمایش محتوای کافی صفحه است و هیچ مشکلی ندارد. ', 15);

        Http::fake([
            'thick-site.test/robots.txt' => Http::response('', 200),
            'thick-site.test/sitemap.xml' => Http::response('', 200),
            'thick-site.test*' => Http::response(
                '<html><head><title>خانه</title><meta name="description" content="x"></head><body><p>'.$longText.'</p></body></html>',
                200,
                ['Content-Type' => 'text/html'],
            ),
        ]);

        $project = SeoProject::factory()->create(['website_url' => 'https://thick-site.test']);

        $audit = app(TechnicalSeoAuditor::class)->audit($project);

        $this->assertFalse($audit->issues()->where('type', SeoTechnicalIssueType::ThinContent->value)->exists());
    }

    public function test_it_flags_two_pages_with_identical_body_content(): void
    {
        // The same visible link text on both pages (even though one
        // title differs from the other) - the duplicate-content check
        // compares the <body>'s own visible text, not the <title>, and
        // it needs to be byte-identical on both pages to prove the
        // check works off real content, not a coincidence.
        $longText = str_repeat('این متن کاملاً یکسان روی دو صفحهٔ مختلف تکرار شده است. ', 15);
        $body = '<body><p>'.$longText.'</p><a href="/twin">دوقلو</a></body>';

        Http::fake([
            'dup-content-site.test/robots.txt' => Http::response('', 200),
            'dup-content-site.test/sitemap.xml' => Http::response('', 200),
            'dup-content-site.test/twin' => Http::response(
                '<html><head><title>دوقلو</title><meta name="description" content="x"></head>'.$body.'</html>',
                200,
                ['Content-Type' => 'text/html'],
            ),
            'dup-content-site.test*' => Http::response(
                '<html><head><title>خانه</title><meta name="description" content="x"></head>'.$body.'</html>',
                200,
                ['Content-Type' => 'text/html'],
            ),
        ]);

        $project = SeoProject::factory()->create(['website_url' => 'https://dup-content-site.test']);

        $audit = app(TechnicalSeoAuditor::class)->audit($project);

        $issue = $audit->issues()->where('type', SeoTechnicalIssueType::DuplicateContent->value)->first();
        $this->assertNotNull($issue);
        $this->assertSame('https://dup-content-site.test/twin', $issue->url);
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
