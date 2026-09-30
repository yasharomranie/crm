<?php

namespace Tests\Feature\Seo;

use App\Filament\Pages\SeoPillarClusterMap;
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

    public function test_a_user_without_permission_is_forbidden(): void
    {
        $user = User::factory()->create();
        $user->assignRole(Role::create(['name' => 'بدون دسترسی نقشه موضوعی']));

        Livewire::actingAs($user)
            ->test(SeoPillarClusterMap::class)
            ->assertForbidden();
    }
}
