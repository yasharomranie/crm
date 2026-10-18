<?php

namespace Tests\Feature\Seo;

use App\Domain\Seo\WpConnector\WpContentBrief;
use App\Domain\Seo\WpConnector\WpFleetStatus;
use App\Domain\Seo\WpConnector\WpPluginPackage;
use App\Domain\Seo\WpConnector\WpUrl;
use App\Filament\Pages\SeoContentGaps;
use App\Filament\Pages\SeoWpFleet;
use App\Models\SeoContentItem;
use App\Models\SeoKeywordProfile;
use App\Models\SeoProject;
use App\Models\SeoWpPost;
use App\Models\SeoWpSite;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class WpFleetAndBriefTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $admin = User::factory()->create();
        $admin->assignRole(Role::firstOrCreate(['name' => 'admin']));

        return $admin;
    }

    private function site(array $attributes = []): SeoWpSite
    {
        return SeoWpSite::create($attributes + [
            'seo_project_id' => SeoProject::factory()->create()->id,
            'status' => SeoWpSite::STATUS_CONNECTED,
            'secret' => 'test-secret',
            'plugin_version' => app(WpPluginPackage::class)->version(),
            'last_seen_at' => now(),
        ]);
    }

    private function wpPost(SeoWpSite $site, string $title, string $url, array $extra = []): SeoWpPost
    {
        return SeoWpPost::factory()->create($extra + [
            'seo_wp_site_id' => $site->id,
            'title' => $title,
            'url' => $url,
            'url_hash' => WpUrl::key($url),
        ]);
    }

    public function test_each_site_gets_the_state_that_needs_attention_first(): void
    {
        $this->site();
        $this->site(['last_error' => 'خطای سرور']);
        $this->site(['last_seen_at' => now()->subDay()]);
        $this->site(['search_engines_discouraged' => true]);
        $this->site(['plugin_version' => '0.0.1']);
        $this->site(['status' => SeoWpSite::STATUS_PENDING]);
        $this->site(['status' => SeoWpSite::STATUS_DISCONNECTED]);

        $overview = app(WpFleetStatus::class)->overview();

        $this->assertSame(
            ['error' => 1, 'silent' => 1, 'discouraged' => 1, 'outdated' => 1, 'pending' => 1, 'disconnected' => 1, 'ok' => 1],
            $overview['counts'],
        );
        $this->assertSame(['error', 'silent', 'discouraged', 'outdated', 'pending', 'disconnected', 'ok'], $overview['rows']->pluck('state')->all());
        $this->assertSame(['error'], app(WpFleetStatus::class)->overview('error')['rows']->pluck('state')->all());
    }

    public function test_the_fleet_counts_pages_and_pages_with_issues(): void
    {
        $site = $this->site();
        $this->wpPost($site, 'الف', 'https://example.test/a', ['issues' => ['orphan']]);
        $this->wpPost($site, 'ب', 'https://example.test/b', ['issues' => []]);

        $row = app(WpFleetStatus::class)->overview()['rows']->first();

        $this->assertSame(2, $row['posts']);
        $this->assertSame(1, $row['posts_with_issues']);
    }

    public function test_the_fleet_page_lists_sites_and_filters_by_state(): void
    {
        $healthy = $this->site();
        $broken = $this->site(['last_error' => 'خطای ویژه‌ی سرور']);

        Livewire::actingAs($this->admin())
            ->test(SeoWpFleet::class)
            ->assertSee($healthy->project->name)
            ->assertSee('خطای ویژه‌ی سرور')
            ->call('filterBy', 'ok')
            ->assertSee($healthy->project->name)
            ->assertDontSee('خطای ویژه‌ی سرور')
            ->call('filterBy', 'bogus')
            ->assertSee('خطای ویژه‌ی سرور');

        $this->assertNotNull($broken->id);
    }

    public function test_the_fleet_page_is_forbidden_without_permission(): void
    {
        $user = User::factory()->create();
        $user->assignRole(Role::create(['name' => 'بدون دسترسی برد وردپرس']));

        Livewire::actingAs($user)->test(SeoWpFleet::class)->assertForbidden();
    }

    public function test_a_brief_lists_pages_to_link_and_related_searches(): void
    {
        $site = $this->site();
        $project = $site->project;
        $this->wpPost($site, 'راهنمای غار علیصدر', 'https://example.test/ali-sadr', ['gsc_clicks' => 40]);
        $this->wpPost($site, 'هتل‌های همدان', 'https://example.test/hotels');
        $this->wpPost($site, 'درباره ما', 'https://example.test/about');
        SeoKeywordProfile::factory()->create(['seo_project_id' => $project->id, 'query' => 'قیمت بلیط غار علیصدر', 'query_hash' => sha1('a'), 'impressions_28d' => 900]);
        SeoKeywordProfile::factory()->create(['seo_project_id' => $project->id, 'query' => 'ساعت بازدید غار علیصدر', 'query_hash' => sha1('b'), 'impressions_28d' => 300]);

        $brief = app(WpContentBrief::class)->build($project, 'ساعت بازدید غار علیصدر');

        $this->assertStringContainsString('https://example.test/ali-sadr', $brief);
        $this->assertStringNotContainsString('https://example.test/about', $brief);
        $this->assertStringContainsString('قیمت بلیط غار علیصدر', $brief);
        $this->assertStringNotContainsString('ساعت بازدید', $brief);
    }

    public function test_a_brief_for_a_rewrite_describes_the_current_page_and_leaves_it_out_of_the_links(): void
    {
        $site = $this->site();
        $page = $this->wpPost($site, 'راهنمای غار علیصدر', 'https://example.test/ali-sadr', [
            'word_count' => 250,
            'headings' => [],
            'issues' => ['thin_content'],
        ]);
        $this->wpPost($site, 'عکس‌های غار علیصدر', 'https://example.test/photos');

        $brief = app(WpContentBrief::class)->build($site->project, 'ساعت بازدید غار علیصدر', $page);

        $this->assertStringContainsString('250 کلمه', $brief);
        $this->assertStringContainsString('بدون سرتیتر', $brief);
        $this->assertStringContainsString('محتوای کم', $brief);
        $this->assertStringContainsString('https://example.test/photos', $brief);
        $this->assertStringNotContainsString('- راهنمای غار علیصدر', $brief);
    }

    public function test_adding_a_gap_to_the_calendar_writes_the_brief_into_the_notes(): void
    {
        $site = $this->site();
        $this->wpPost($site, 'راهنمای غار علیصدر', 'https://example.test/ali-sadr', ['seo' => ['focus_keyword' => 'غار علیصدر']]);
        SeoKeywordProfile::factory()->create([
            'seo_project_id' => $site->seo_project_id, 'query' => 'ساعت بازدید غار علیصدر', 'query_hash' => sha1('x'),
            'current_position' => 25, 'impressions_28d' => 400,
        ]);

        Livewire::actingAs($this->admin())
            ->test(SeoContentGaps::class, ['projectId' => $site->seo_project_id])
            ->call('addToCalendar', 'new', 'ساعت بازدید غار علیصدر');

        $this->assertStringContainsString('https://example.test/ali-sadr', SeoContentItem::sole()->notes);
    }
}
