<?php

namespace Tests\Feature\Seo;

use App\Enums\SeoKeywordSuggestionStatus;
use App\Filament\Pages\SeoPillarClusterMap;
use App\Models\SeoContentItem;
use App\Models\SeoKeywordProfile;
use App\Models\SeoKeywordSuggestion;
use App\Models\SeoProject;
use App\Models\SeoTopicCluster;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SeoPillarClusterMapPageTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $admin = User::factory()->create();
        $admin->assignRole(Role::firstOrCreate(['name' => 'admin']));

        return $admin;
    }

    public function test_it_shows_a_prompt_when_no_project_is_selected(): void
    {
        Livewire::actingAs($this->admin())
            ->test(SeoPillarClusterMap::class)
            ->assertSee('برای دیدن نقشه‌ی موضوعی، ابتدا یک پروژه انتخاب کن.');
    }

    public function test_selecting_a_project_shows_its_clustered_keywords(): void
    {
        $project = SeoProject::factory()->create();
        $cluster = SeoTopicCluster::factory()->create(['seo_project_id' => $project->id, 'name' => 'اقامت']);
        SeoKeywordProfile::factory()->create(['seo_project_id' => $project->id, 'topic_cluster_id' => $cluster->id, 'query' => 'اجاره ویلا همدان']);
        SeoKeywordSuggestion::factory()->create(['seo_project_id' => $project->id, 'topic_cluster_id' => $cluster->id, 'keyword' => 'بهترین ویلاهای همدان']);

        Livewire::actingAs($this->admin())
            ->test(SeoPillarClusterMap::class, ['projectId' => $project->id])
            ->assertSee('اقامت')
            ->assertSee('اجاره ویلا همدان')
            ->assertSee('بهترین ویلاهای همدان');
    }

    public function test_a_profile_with_a_landing_page_shows_a_link_to_it(): void
    {
        $project = SeoProject::factory()->create();
        $cluster = SeoTopicCluster::factory()->create(['seo_project_id' => $project->id]);
        SeoKeywordProfile::factory()->create([
            'seo_project_id' => $project->id,
            'topic_cluster_id' => $cluster->id,
            'query' => 'اجاره ویلا همدان',
            'landing_page' => 'https://ganjnameh.example.com/villa',
        ]);

        Livewire::actingAs($this->admin())
            ->test(SeoPillarClusterMap::class, ['projectId' => $project->id])
            ->assertSee('1 صفحه‌ی زنده')
            ->assertSeeHtml('https://ganjnameh.example.com/villa');
    }

    public function test_a_profiles_current_position_is_shown_next_to_it(): void
    {
        $project = SeoProject::factory()->create();
        $cluster = SeoTopicCluster::factory()->create(['seo_project_id' => $project->id]);
        SeoKeywordProfile::factory()->create([
            'seo_project_id' => $project->id,
            'topic_cluster_id' => $cluster->id,
            'query' => 'اجاره ویلا همدان',
            'current_position' => 6.4,
        ]);

        Livewire::actingAs($this->admin())
            ->test(SeoPillarClusterMap::class, ['projectId' => $project->id])
            ->assertSee('رتبه 6.4');
    }

    public function test_a_profile_with_a_ctr_opportunity_score_shows_a_growth_warning(): void
    {
        $project = SeoProject::factory()->create();
        $cluster = SeoTopicCluster::factory()->create(['seo_project_id' => $project->id]);
        SeoKeywordProfile::factory()->create([
            'seo_project_id' => $project->id,
            'topic_cluster_id' => $cluster->id,
            'query' => 'اجاره ویلا همدان',
            'current_position' => 6.4,
            'opportunity_score' => 42,
        ]);

        Livewire::actingAs($this->admin())
            ->test(SeoPillarClusterMap::class, ['projectId' => $project->id])
            ->assertSee('فرصت رشد و کلیک بیشتر')
            ->assertSeeHtml('42 کلیک بیشتر در ماه')
            ->assertSeeHtml('bg-purple-50');
    }

    public function test_a_clusters_header_shows_its_opportunity_count_in_purple(): void
    {
        $project = SeoProject::factory()->create();
        $cluster = SeoTopicCluster::factory()->create(['seo_project_id' => $project->id, 'name' => 'اقامت']);
        SeoKeywordProfile::factory()->create([
            'seo_project_id' => $project->id,
            'topic_cluster_id' => $cluster->id,
            'query' => 'اجاره ویلا همدان',
            'opportunity_score' => 42,
        ]);
        SeoKeywordProfile::factory()->create([
            'seo_project_id' => $project->id,
            'topic_cluster_id' => $cluster->id,
            'query' => 'اجاره سوییت همدان',
            'opportunity_score' => 15,
        ]);

        Livewire::actingAs($this->admin())
            ->test(SeoPillarClusterMap::class, ['projectId' => $project->id])
            ->assertSee('2 فرصت رشد')
            ->assertSeeHtml('bg-purple-50');
    }

    public function test_a_profile_without_a_ctr_opportunity_score_shows_no_growth_warning(): void
    {
        $project = SeoProject::factory()->create();
        $cluster = SeoTopicCluster::factory()->create(['seo_project_id' => $project->id]);
        SeoKeywordProfile::factory()->create([
            'seo_project_id' => $project->id,
            'topic_cluster_id' => $cluster->id,
            'query' => 'اجاره ویلا همدان',
            'opportunity_score' => null,
        ]);

        Livewire::actingAs($this->admin())
            ->test(SeoPillarClusterMap::class, ['projectId' => $project->id])
            // The legend at the bottom of the tree tab always mentions
            // "فرصت رشد و کلیک بیشتر", so assert on the per-keyword
            // tooltip text instead of that phrase.
            ->assertDontSee('این کلمه در رتبه');
    }

    public function test_tree_data_marks_only_profiles_with_a_positive_opportunity_score(): void
    {
        $project = SeoProject::factory()->create(['name' => 'گنج‌نامه']);
        $cluster = SeoTopicCluster::factory()->create(['seo_project_id' => $project->id, 'name' => 'غار علیصدر']);
        SeoKeywordProfile::factory()->create([
            'seo_project_id' => $project->id,
            'topic_cluster_id' => $cluster->id,
            'query' => 'قیمت بلیط غار علیصدر',
            'opportunity_score' => 42,
        ]);
        SeoKeywordProfile::factory()->create([
            'seo_project_id' => $project->id,
            'topic_cluster_id' => $cluster->id,
            'query' => 'ساعت کاری غار علیصدر',
            'opportunity_score' => null,
        ]);

        $component = Livewire::actingAs($this->admin())
            ->test(SeoPillarClusterMap::class, ['projectId' => $project->id]);

        $tree = $component->instance()->treeData();

        $leaves = collect($tree['children'][0]['children'])->keyBy('name');
        $this->assertTrue($leaves['قیمت بلیط غار علیصدر']['hasOpportunity']);
        $this->assertFalse($leaves['ساعت کاری غار علیصدر']['hasOpportunity']);
    }

    public function test_tree_data_marks_only_keywords_that_are_in_a_warning_pair(): void
    {
        $project = SeoProject::factory()->create(['name' => 'گنج‌نامه']);
        $cluster = SeoTopicCluster::factory()->create(['seo_project_id' => $project->id, 'name' => 'غار علیصدر']);
        SeoKeywordSuggestion::factory()->create(['seo_project_id' => $project->id, 'topic_cluster_id' => $cluster->id, 'keyword' => 'قیمت بلیط غار علیصدر']);
        SeoKeywordSuggestion::factory()->create(['seo_project_id' => $project->id, 'topic_cluster_id' => $cluster->id, 'keyword' => 'ساعت کاری غار علیصدر']);
        SeoKeywordSuggestion::factory()->create(['seo_project_id' => $project->id, 'topic_cluster_id' => $cluster->id, 'keyword' => 'خرید سوغات همدان']);

        $component = Livewire::actingAs($this->admin())
            ->test(SeoPillarClusterMap::class, ['projectId' => $project->id]);

        $tree = $component->instance()->treeData();

        $this->assertSame('گنج‌نامه', $tree['name']);
        $this->assertCount(1, $tree['children']);
        $this->assertSame('غار علیصدر', $tree['children'][0]['name']);

        $leaves = collect($tree['children'][0]['children'])->keyBy('name');
        $this->assertTrue($leaves['قیمت بلیط غار علیصدر']['hasWarning']);
        $this->assertTrue($leaves['ساعت کاری غار علیصدر']['hasWarning']);
        $this->assertFalse($leaves['خرید سوغات همدان']['hasWarning']);
    }

    public function test_tab_labels_show_the_total_warning_and_opportunity_counts(): void
    {
        $project = SeoProject::factory()->create();
        $cluster = SeoTopicCluster::factory()->create(['seo_project_id' => $project->id]);
        // Only these two overlap (share "بلیط" and "استخر") - everything
        // else below uses unrelated words so it doesn't add extra,
        // unintended warnings on top of this one pair.
        SeoKeywordSuggestion::factory()->create(['seo_project_id' => $project->id, 'topic_cluster_id' => $cluster->id, 'keyword' => 'قیمت بلیط استخر']);
        SeoKeywordSuggestion::factory()->create(['seo_project_id' => $project->id, 'topic_cluster_id' => $cluster->id, 'keyword' => 'نرخ بلیط استخر']);
        SeoKeywordProfile::factory()->create(['seo_project_id' => $project->id, 'topic_cluster_id' => $cluster->id, 'query' => 'خرید لوازم آرایشی زنانه', 'opportunity_score' => 10]);
        SeoKeywordProfile::factory()->create(['seo_project_id' => $project->id, 'topic_cluster_id' => $cluster->id, 'query' => 'فروش عطر مردانه', 'opportunity_score' => 25]);

        $component = Livewire::actingAs($this->admin())
            ->test(SeoPillarClusterMap::class, ['projectId' => $project->id]);

        $this->assertSame(1, $component->instance()->totalWarningsCount());
        $this->assertSame(2, $component->instance()->totalOpportunitiesCount());

        $component->assertSeeHtmlInOrder(['نمای درختی', '1', '2', 'لیست خوشه‌ها', '1', '2']);
    }

    public function test_tab_labels_show_no_count_badges_when_there_are_none(): void
    {
        $project = SeoProject::factory()->create();
        $cluster = SeoTopicCluster::factory()->create(['seo_project_id' => $project->id]);
        SeoKeywordProfile::factory()->create(['seo_project_id' => $project->id, 'topic_cluster_id' => $cluster->id]);

        Livewire::actingAs($this->admin())
            ->test(SeoPillarClusterMap::class, ['projectId' => $project->id])
            ->assertDontSeeHtml('bg-danger-50')
            ->assertDontSeeHtml('bg-purple-50');
    }

    public function test_the_tree_tab_is_shown_alongside_the_list_tab(): void
    {
        $project = SeoProject::factory()->create();
        $cluster = SeoTopicCluster::factory()->create(['seo_project_id' => $project->id, 'name' => 'اقامت']);
        SeoKeywordProfile::factory()->create(['seo_project_id' => $project->id, 'topic_cluster_id' => $cluster->id, 'query' => 'اجاره ویلا همدان']);

        Livewire::actingAs($this->admin())
            ->test(SeoPillarClusterMap::class, ['projectId' => $project->id])
            ->assertSee('نمای درختی')
            ->assertSee('لیست خوشه‌ها');
    }

    public function test_adding_a_suggestion_to_the_content_calendar_creates_a_content_item_and_approves_it(): void
    {
        $project = SeoProject::factory()->create();
        $cluster = SeoTopicCluster::factory()->create(['seo_project_id' => $project->id]);
        $suggestion = SeoKeywordSuggestion::factory()->create([
            'seo_project_id' => $project->id,
            'topic_cluster_id' => $cluster->id,
            'keyword' => 'اجاره ویلا نزدیک غار علیصدر',
            'suggested_content_type' => 'صفحه فرود',
            'status' => SeoKeywordSuggestionStatus::New,
        ]);

        Livewire::actingAs($this->admin())
            ->test(SeoPillarClusterMap::class, ['projectId' => $project->id])
            ->callAction('addSuggestionToContentCalendar', data: [
                'title' => 'صفحه فرود اجاره ویلا',
            ], arguments: ['suggestionId' => $suggestion->id]);

        $item = SeoContentItem::firstOrFail();
        $this->assertSame($suggestion->id, $item->keyword_suggestion_id);
        $this->assertSame($project->id, $item->seo_project_id);
        $this->assertSame('صفحه فرود اجاره ویلا', $item->title);
        $this->assertSame('اجاره ویلا نزدیک غار علیصدر', $item->target_keyword);
        $this->assertSame('صفحه فرود', $item->content_type);

        $this->assertSame(SeoKeywordSuggestionStatus::Approved, $suggestion->refresh()->status);
    }

    public function test_a_user_without_permission_is_forbidden(): void
    {
        $user = User::factory()->create();
        $user->assignRole(Role::create(['name' => 'بدون دسترسی نقشه موضوعی']));

        Livewire::actingAs($user)
            ->test(SeoPillarClusterMap::class)
            ->assertForbidden();
    }
}
