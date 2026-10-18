<?php

namespace Tests\Feature\Seo;

use App\Domain\Seo\WpConnector\WpPostIssues;
use App\Domain\Seo\WpConnector\WpSignature;
use App\Domain\Seo\WpConnector\WpSiteAnalyzer;
use App\Domain\Seo\WpConnector\WpUrl;
use App\Filament\Resources\SeoWpPosts\Pages\ManageSeoWpPosts;
use App\Jobs\AnalyzeWpSiteJob;
use App\Models\SeoPageMetric;
use App\Models\SeoWpPost;
use App\Models\SeoWpSite;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Tests\TestCase;

class WpSiteAnalysisTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_same_page_written_in_different_ways_has_one_key(): void
    {
        $key = WpUrl::key('https://example.test/%D9%85%D9%82%D8%A7%D9%84%D9%87');

        $this->assertSame($key, WpUrl::key('http://www.example.test/مقاله/'));
        $this->assertSame($key, WpUrl::key('https://EXAMPLE.test/مقاله?utm_source=x#top'));
        $this->assertNotSame($key, WpUrl::key('https://example.test/مقاله-دیگر'));
        $this->assertTrue(WpUrl::isFrontPage('https://example.test/'));
        $this->assertFalse(WpUrl::isFrontPage('https://example.test/blog'));
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function facts(array $overrides = []): array
    {
        return $overrides + [
            'status' => 'publish',
            'post_type' => 'post',
            'url' => 'https://example.test/article',
            'word_count' => 900,
            'seo' => ['focus_keyword' => 'همدان', 'description' => 'توضیح', 'noindex' => false],
            'images' => ['count' => 2, 'missing_alt' => 0],
            'inbound' => 3,
            'published_at' => now()->subDays(90),
            'gsc' => ['clicks' => 50, 'impressions' => 1000, 'ctr' => 0.05, 'position' => 4.2, 'prev_clicks' => 50],
            'has_gsc' => true,
        ];
    }

    public function test_a_healthy_page_has_no_issues(): void
    {
        $this->assertSame([], WpPostIssues::detect($this->facts()));
    }

    public function test_each_problem_is_detected(): void
    {
        $detect = fn (array $overrides) => WpPostIssues::detect($this->facts($overrides));

        $this->assertContains('noindex', $detect(['seo' => ['noindex' => true, 'description' => 'x', 'focus_keyword' => 'y']]));
        $this->assertContains('no_meta_description', $detect(['seo' => ['noindex' => false, 'description' => '', 'focus_keyword' => 'y']]));
        $this->assertContains('no_focus_keyword', $detect(['seo' => ['noindex' => false, 'description' => 'x', 'focus_keyword' => '']]));
        $this->assertContains('thin_content', $detect(['word_count' => 120]));
        $this->assertContains('images_missing_alt', $detect(['images' => ['count' => 3, 'missing_alt' => 2]]));
        $this->assertContains('orphan', $detect(['inbound' => 0]));
        $this->assertContains('not_in_search', $detect(['gsc' => null]));
        $this->assertContains('traffic_drop', $detect(['gsc' => ['clicks' => 20, 'impressions' => 900, 'ctr' => 0.02, 'position' => 5.0, 'prev_clicks' => 100]]));
        $this->assertContains('low_ctr', $detect(['gsc' => ['clicks' => 2, 'impressions' => 900, 'ctr' => 0.002, 'position' => 6.0, 'prev_clicks' => 2]]));
        $this->assertContains('striking_distance', $detect(['gsc' => ['clicks' => 5, 'impressions' => 400, 'ctr' => 0.012, 'position' => 14.0, 'prev_clicks' => 5]]));
    }

    public function test_checks_without_data_are_skipped_and_unpublished_pages_are_not_judged(): void
    {
        $this->assertNotContains('orphan', WpPostIssues::detect($this->facts(['inbound' => null])));
        $this->assertNotContains('no_meta_description', WpPostIssues::detect($this->facts(['seo' => null])));
        $this->assertNotContains('not_in_search', WpPostIssues::detect($this->facts(['gsc' => null, 'has_gsc' => false])));
        $this->assertNotContains('not_in_search', WpPostIssues::detect($this->facts(['gsc' => null, 'published_at' => now()->subDays(3)])));
        $this->assertNotContains('orphan', WpPostIssues::detect($this->facts(['inbound' => 0, 'url' => 'https://example.test/'])));
        $this->assertSame([], WpPostIssues::detect($this->facts(['status' => 'draft', 'word_count' => 10, 'inbound' => 0])));
    }

    private function metric(SeoWpSite $site, string $url, string $daysAgo, int $clicks, int $impressions, float $position): void
    {
        SeoPageMetric::create([
            'seo_project_id' => $site->seo_project_id,
            'date' => now()->subDays((int) $daysAgo)->toDateString(),
            'page_url' => $url,
            'page_url_hash' => sha1($url.$daysAgo),
            'clicks' => $clicks,
            'impressions' => $impressions,
            'ctr' => $impressions ? $clicks / $impressions : 0,
            'position' => $position,
        ]);
    }

    public function test_the_analyzer_counts_inbound_links_and_matches_search_console(): void
    {
        $site = SeoWpSite::factory()->connected()->create(['scopes' => ['content', 'seo_meta', 'structure']]);
        $seo = ['focus_keyword' => 'همدان', 'description' => 'توضیح', 'noindex' => false];

        $a = SeoWpPost::factory()->create([
            'seo_wp_site_id' => $site->id,
            'url' => 'https://example.test/مقاله-همدان',
            'url_hash' => WpUrl::key('https://example.test/مقاله-همدان'),
            'published_at' => now()->subDays(90),
            'word_count' => 800,
            'seo' => $seo,
            'internal_links' => [['url' => 'https://example.test/b/', 'anchor' => 'ب', 'nofollow' => false]],
        ]);
        $b = SeoWpPost::factory()->create([
            'seo_wp_site_id' => $site->id,
            'url' => 'https://example.test/b',
            'url_hash' => WpUrl::key('https://example.test/b'),
            'published_at' => now()->subDays(90),
            'word_count' => 800,
            'seo' => $seo,
            'internal_links' => [],
        ]);
        $c = SeoWpPost::factory()->create([
            'seo_wp_site_id' => $site->id,
            'url' => 'https://example.test/c',
            'url_hash' => WpUrl::key('https://example.test/c'),
            'published_at' => now()->subDays(90),
            'word_count' => 800,
            'seo' => $seo,
            'internal_links' => [['url' => 'https://example.test/c', 'anchor' => 'خودش', 'nofollow' => false]],
        ]);

        $encoded = 'https://example.test/%D9%85%D9%82%D8%A7%D9%84%D9%87-%D9%87%D9%85%D8%AF%D8%A7%D9%86/';
        $this->metric($site, $encoded, '10', 20, 400, 5.0);
        $this->metric($site, $encoded, '11', 10, 100, 10.0);
        $this->metric($site, $encoded, '45', 100, 800, 4.0);

        app(WpSiteAnalyzer::class)->analyze($site);

        $a->refresh();
        $this->assertSame(30, $a->gsc_clicks);
        $this->assertSame(500, $a->gsc_impressions);
        $this->assertSame(100, $a->gsc_prev_clicks);
        $this->assertEqualsWithDelta(6.0, (float) $a->gsc_position, 0.01);
        $this->assertContains('traffic_drop', $a->issues);
        $this->assertSame(0, $a->inbound_links);
        $this->assertContains('orphan', $a->issues);

        $this->assertSame(1, $b->refresh()->inbound_links);
        $this->assertNotContains('orphan', $b->issues);
        $this->assertContains('not_in_search', $b->issues);

        $this->assertSame(0, $c->refresh()->inbound_links, 'a link to itself does not count');
        $this->assertNotNull($site->refresh()->analyzed_at);
    }

    public function test_without_the_structure_scope_links_are_not_judged(): void
    {
        $site = SeoWpSite::factory()->connected()->create(['scopes' => ['content']]);
        $post = SeoWpPost::factory()->create(['seo_wp_site_id' => $site->id, 'published_at' => now()->subDays(90)]);

        app(WpSiteAnalyzer::class)->analyze($site);

        $post->refresh();
        $this->assertNull($post->inbound_links);
        $this->assertNotContains('orphan', $post->issues);
    }

    public function test_a_sync_queues_an_analysis_and_records_the_discourage_flag(): void
    {
        Queue::fake();
        $site = SeoWpSite::factory()->connected()->create();
        $body = json_encode([
            'site' => ['search_engines_discouraged' => true],
            'posts' => [['wp_id' => 1, 'post_type' => 'post', 'status' => 'publish', 'url' => 'https://example.test/a', 'title' => 'الف']],
        ], JSON_UNESCAPED_UNICODE);
        $timestamp = time();

        $this->call('POST', '/api/v1/wp-connector/sync', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_X_WP_SITE' => (string) $site->id,
            'HTTP_X_WP_TIMESTAMP' => (string) $timestamp,
            'HTTP_X_WP_SIGNATURE' => WpSignature::sign($site->secret, $timestamp, $body),
        ], $body)->assertOk();

        Queue::assertPushed(AnalyzeWpSiteJob::class, fn (AnalyzeWpSiteJob $job) => $job->siteId === $site->id);
        $this->assertTrue($site->refresh()->search_engines_discouraged);
    }

    public function test_the_content_page_lists_posts_and_filters_by_issue(): void
    {
        $site = SeoWpSite::factory()->connected()->create();
        $orphan = SeoWpPost::factory()->create(['seo_wp_site_id' => $site->id, 'title' => 'مقاله‌ی یتیم', 'issues' => ['orphan']]);
        $healthy = SeoWpPost::factory()->create(['seo_wp_site_id' => $site->id, 'title' => 'مقاله‌ی سالم', 'issues' => []]);

        $this->actingAs(User::factory()->create());

        Livewire::test(ManageSeoWpPosts::class)
            ->assertCanSeeTableRecords([$orphan, $healthy])
            ->filterTable('issue', 'orphan')
            ->assertCanSeeTableRecords([$orphan])
            ->assertCanNotSeeTableRecords([$healthy]);
    }
}
