<?php

namespace Tests\Feature\Seo;

use App\Domain\Seo\WpConnector\WpContentInventory;
use App\Domain\Seo\WpConnector\WpCoverageGaps;
use App\Domain\Seo\WpConnector\WpPublishedContentMatcher;
use App\Domain\Seo\WpConnector\WpUrl;
use App\Enums\SeoContentItemStatus;
use App\Jobs\AnalyzeWpSiteJob;
use App\Models\SeoContentItem;
use App\Models\SeoKeywordProfile;
use App\Models\SeoPageMetric;
use App\Models\SeoProject;
use App\Models\SeoWpPost;
use App\Models\SeoWpSite;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WpPublishedContentMatcherTest extends TestCase
{
    use RefreshDatabase;

    private function site(): SeoWpSite
    {
        return SeoWpSite::factory()->connected()->create(['seo_project_id' => SeoProject::factory()->create()->id]);
    }

    private function wpPost(SeoWpSite $site, string $title, string $url, array $extra = []): SeoWpPost
    {
        return SeoWpPost::factory()->create($extra + [
            'seo_wp_site_id' => $site->id,
            'title' => $title,
            'url' => $url,
            'url_hash' => WpUrl::key($url),
            'published_at' => now(),
            'modified_at' => now(),
        ]);
    }

    private function item(SeoWpSite $site, string $keyword, SeoContentItemStatus $status = SeoContentItemStatus::Planned, int $createdDaysAgo = 5): SeoContentItem
    {
        return SeoContentItem::factory()->create([
            'seo_project_id' => $site->seo_project_id,
            'target_keyword' => $keyword,
            'status' => $status,
            'created_at' => now()->subDays($createdDaysAgo),
        ]);
    }

    public function test_the_stem_ignores_persian_endings_and_filler_words(): void
    {
        $this->assertSame(WpContentInventory::canonical('راهنمای سفر به همدان'), WpContentInventory::canonical('راهنما سفر همدان'));
        $this->assertSame(WpContentInventory::canonical('غارهای همدان'), WpContentInventory::canonical('غار همدان'));
        $this->assertSame(['همدان'], WpContentInventory::tokens('همدان'));
        $this->assertSame(['ایران'], WpContentInventory::tokens('ایران'));
    }

    public function test_a_search_a_page_title_covers_in_another_form_is_not_a_gap(): void
    {
        $site = $this->site();
        $this->wpPost($site, 'راهنمای سفر به همدان', 'https://example.test/guide');
        SeoKeywordProfile::factory()->create([
            'seo_project_id' => $site->seo_project_id, 'query' => 'راهنما سفر همدان', 'query_hash' => sha1('q'),
            'current_position' => 30, 'impressions_28d' => 400,
        ]);

        $this->assertTrue(app(WpCoverageGaps::class)->analyze($site->project)['uncovered']->isEmpty());
    }

    public function test_a_new_article_that_appears_on_the_site_marks_its_calendar_row_published(): void
    {
        $site = $this->site();
        $item = $this->item($site, 'ساعت بازدید غار علیصدر');
        $post = $this->wpPost($site, 'ساعت بازدید از غار علیصدر', 'https://example.test/visit-hours', ['published_at' => now()->subDay()]);

        $this->assertSame(1, app(WpPublishedContentMatcher::class)->match($site->project));

        $item->refresh();
        $this->assertSame(SeoContentItemStatus::Published, $item->status);
        $this->assertSame($post->url, $item->published_url);
        $this->assertTrue($item->published_at->equalTo($post->fresh()->published_at));
    }

    public function test_a_page_published_before_the_row_was_made_does_not_count(): void
    {
        $site = $this->site();
        $item = $this->item($site, 'ساعت بازدید غار علیصدر', createdDaysAgo: 2);
        $this->wpPost($site, 'ساعت بازدید غار علیصدر', 'https://example.test/old', ['published_at' => now()->subYear(), 'modified_at' => now()->subYear()]);

        $this->assertSame(0, app(WpPublishedContentMatcher::class)->match($site->project));
        $this->assertSame(SeoContentItemStatus::Planned, $item->fresh()->status);
    }

    public function test_a_rewrite_row_is_done_when_the_page_was_edited_after_it(): void
    {
        $site = $this->site();
        $rewrite = $this->item($site, 'ساعت بازدید غار', SeoContentItemStatus::NeedsUpdate, 3);
        $edited = $this->wpPost($site, 'راهنمای ساعت بازدید غار', 'https://example.test/cave', [
            'published_at' => now()->subYear(),
            'modified_at' => now()->subDay(),
        ]);

        $this->assertSame(1, app(WpPublishedContentMatcher::class)->match($site->project));

        $rewrite->refresh();
        $this->assertSame(SeoContentItemStatus::Published, $rewrite->status);
        $this->assertTrue($rewrite->published_at->equalTo($edited->fresh()->modified_at));
    }

    public function test_a_rewrite_row_stays_open_while_the_page_is_untouched(): void
    {
        $site = $this->site();
        $rewrite = $this->item($site, 'ساعت بازدید غار', SeoContentItemStatus::NeedsUpdate, 3);
        $this->wpPost($site, 'راهنمای ساعت بازدید غار', 'https://example.test/cave', [
            'published_at' => now()->subYear(),
            'modified_at' => now()->subMonths(2),
        ]);

        $this->assertSame(0, app(WpPublishedContentMatcher::class)->match($site->project));
        $this->assertSame(SeoContentItemStatus::NeedsUpdate, $rewrite->fresh()->status);
    }

    public function test_one_page_is_never_given_to_two_rows(): void
    {
        $site = $this->site();
        $this->item($site, 'ساعت بازدید غار علیصدر');
        $this->item($site, 'ساعت بازدید غار علیصدر');
        $this->wpPost($site, 'ساعت بازدید غار علیصدر', 'https://example.test/visit');

        $this->assertSame(1, app(WpPublishedContentMatcher::class)->match($site->project));
        $this->assertSame(1, SeoContentItem::where('status', SeoContentItemStatus::Published)->count());
    }

    public function test_the_address_uses_search_consoles_spelling_when_it_has_one(): void
    {
        $site = $this->site();
        $item = $this->item($site, 'ساعت بازدید غار علیصدر');
        $this->wpPost($site, 'ساعت بازدید غار علیصدر', 'https://example.test/visit');
        SeoPageMetric::create([
            'seo_project_id' => $site->seo_project_id, 'date' => now()->subDays(4)->toDateString(),
            'page_url' => 'https://www.example.test/visit/', 'page_url_hash' => sha1('https://www.example.test/visit/'),
            'clicks' => 3, 'impressions' => 90, 'ctr' => 0.03, 'position' => 12,
        ]);

        app(WpPublishedContentMatcher::class)->match($site->project);

        $this->assertSame('https://www.example.test/visit/', $item->fresh()->published_url);
    }

    public function test_the_analysis_job_runs_the_matcher(): void
    {
        $site = $this->site();
        $item = $this->item($site, 'ساعت بازدید غار علیصدر');
        $this->wpPost($site, 'ساعت بازدید غار علیصدر', 'https://example.test/visit');

        AnalyzeWpSiteJob::dispatchSync($site->id);

        $this->assertSame(SeoContentItemStatus::Published, $item->fresh()->status);
    }
}
