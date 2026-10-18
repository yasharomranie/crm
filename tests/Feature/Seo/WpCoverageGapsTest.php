<?php

namespace Tests\Feature\Seo;

use App\Domain\Seo\WpConnector\WpCoverageGaps;
use App\Domain\Seo\WpConnector\WpUrl;
use App\Enums\SeoContentItemStatus;
use App\Filament\Pages\SeoContentGaps;
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

class WpCoverageGapsTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $admin = User::factory()->create();
        $admin->assignRole(Role::firstOrCreate(['name' => 'admin']));

        return $admin;
    }

    private function project(): SeoProject
    {
        return SeoProject::factory()->create();
    }

    private function wpPost(SeoProject $project, string $title, ?string $focus = null, string $url = 'https://example.test/p'): SeoWpPost
    {
        $site = SeoWpSite::firstOrCreate(['seo_project_id' => $project->id], ['status' => SeoWpSite::STATUS_CONNECTED]);

        return SeoWpPost::factory()->create([
            'seo_wp_site_id' => $site->id,
            'title' => $title,
            'seo' => $focus ? ['focus_keyword' => $focus] : null,
            'url' => $url,
            'url_hash' => WpUrl::key($url),
        ]);
    }

    private function profile(SeoProject $project, string $query, float $position, int $impressions = 400, ?string $landing = null): SeoKeywordProfile
    {
        return SeoKeywordProfile::factory()->create([
            'seo_project_id' => $project->id,
            'query' => $query,
            'query_hash' => sha1($query),
            'current_position' => $position,
            'impressions_28d' => $impressions,
            'landing_page' => $landing,
        ]);
    }

    public function test_a_search_no_page_targets_is_an_uncovered_gap(): void
    {
        $project = $this->project();
        $this->wpPost($project, 'راهنمای غار علیصدر', 'غار علیصدر');
        $this->profile($project, 'اجاره ویلا نزدیک همدان', 25.0);

        $gaps = app(WpCoverageGaps::class)->analyze($project);

        $this->assertSame(['اجاره ویلا نزدیک همدان'], $gaps['uncovered']->pluck('query')->all());
        $this->assertTrue($gaps['improve']->isEmpty());
    }

    public function test_a_search_a_page_ranks_for_on_page_two_is_one_to_improve(): void
    {
        $project = $this->project();
        $post = $this->wpPost($project, 'راهنمای غار علیصدر', 'غار علیصدر', 'https://example.test/ali-sadr');
        $this->profile($project, 'ساعت بازدید غار علیصدر', 14.0, 300, 'https://example.test/ali-sadr/');

        $gaps = app(WpCoverageGaps::class)->analyze($project);

        $this->assertTrue($gaps['uncovered']->isEmpty());
        $this->assertSame(1, $gaps['improve']->count());
        $this->assertSame($post->id, $gaps['improve']->first()['post_id']);
    }

    public function test_targeted_well_ranked_planned_and_quiet_searches_are_left_out(): void
    {
        $project = $this->project();
        $this->wpPost($project, 'راهنمای سفر به همدان', 'سفر به همدان');
        $this->profile($project, 'سفر به همدان', 30.0);
        $this->profile($project, 'همدان سفر', 40.0);
        $this->profile($project, 'عبارت در رتبه خوب', 4.0);
        $this->profile($project, 'عبارت کم‌نمایش', 40.0, 4);
        $this->profile($project, 'عبارت برنامه‌ریزی‌شده', 40.0);
        SeoContentItem::factory()->create(['seo_project_id' => $project->id, 'target_keyword' => 'عبارت برنامه‌ریزی‌شده']);

        $gaps = app(WpCoverageGaps::class)->analyze($project);

        $this->assertSame([], $gaps['uncovered']->pluck('query')->all());
    }

    public function test_a_focus_keyword_in_another_spelling_still_counts_as_targeted(): void
    {
        $project = $this->project();
        $this->wpPost($project, 'مقاله', 'اجاره ويلا');
        $this->profile($project, 'اجاره ویلا', 35.0);

        $this->assertTrue(app(WpCoverageGaps::class)->analyze($project)['uncovered']->isEmpty());
    }

    public function test_without_pages_or_searches_it_says_what_is_missing(): void
    {
        $project = $this->project();

        $this->assertFalse(app(WpCoverageGaps::class)->analyze($project)['has_pages']);

        $this->wpPost($project, 'مقاله');

        $gaps = app(WpCoverageGaps::class)->analyze($project);
        $this->assertTrue($gaps['has_pages']);
        $this->assertFalse($gaps['has_searches']);
    }

    public function test_the_page_lists_gaps_and_adds_a_row_to_the_calendar(): void
    {
        $project = $this->project();
        $this->wpPost($project, 'مقاله‌ی دیگر', 'چیز دیگر');
        $this->profile($project, 'اجاره ویلا نزدیک همدان', 25.0);

        $page = Livewire::actingAs($this->admin())
            ->test(SeoContentGaps::class, ['projectId' => $project->id])
            ->assertSee('اجاره ویلا نزدیک همدان')
            ->call('addToCalendar', 'new', 'اجاره ویلا نزدیک همدان');

        $item = SeoContentItem::sole();
        $this->assertSame('اجاره ویلا نزدیک همدان', $item->target_keyword);
        $this->assertSame(SeoContentItemStatus::Idea, $item->status);

        $page->assertDontSee('addToCalendar(\'new\'', false);
        $this->assertTrue(app(WpCoverageGaps::class)->analyze($project)['uncovered']->isEmpty());
    }

    public function test_an_improve_row_becomes_a_needs_update_item(): void
    {
        $project = $this->project();
        $post = $this->wpPost($project, 'راهنمای غار', 'غار', 'https://example.test/cave');
        $this->profile($project, 'ساعت بازدید غار', 14.0, 300, 'https://example.test/cave');

        Livewire::actingAs($this->admin())
            ->test(SeoContentGaps::class, ['projectId' => $project->id])
            ->call('addToCalendar', 'improve', 'ساعت بازدید غار', $post->id);

        $item = SeoContentItem::sole();
        $this->assertSame(SeoContentItemStatus::NeedsUpdate, $item->status);
        $this->assertStringStartsWith('بازنویسی: راهنمای غار', $item->title);
    }

    public function test_a_user_without_permission_is_forbidden(): void
    {
        $user = User::factory()->create();
        $user->assignRole(Role::create(['name' => 'بدون دسترسی خلأ پوشش']));

        Livewire::actingAs($user)->test(SeoContentGaps::class)->assertForbidden();
    }

    public function test_a_small_site_whose_searches_are_all_quiet_is_told_so_and_can_lower_the_bar(): void
    {
        $project = $this->project();
        $this->wpPost($project, 'مقاله‌ی دیگر', 'چیز دیگر');
        $this->profile($project, 'اجاره ویلا نزدیک همدان', 25.0, 6);

        $gaps = app(WpCoverageGaps::class)->analyze($project);
        $this->assertTrue($gaps['has_searches']);
        $this->assertSame(1, $gaps['searches_total']);
        $this->assertSame(0, $gaps['searches_considered']);

        $admin = $this->admin();

        Livewire::actingAs($admin)
            ->test(SeoContentGaps::class, ['projectId' => $project->id])
            ->assertSee('هیچ‌کدام در ۲۸ روز اخیر 10 ایمپرشن یا بیشتر نداشته است')
            ->set('minImpressions', 5)
            ->assertSee('اجاره ویلا نزدیک همدان');
    }
}
