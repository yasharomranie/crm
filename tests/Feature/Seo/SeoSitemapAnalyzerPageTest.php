<?php

namespace Tests\Feature\Seo;

use App\Filament\Pages\SeoSitemapAnalyzer;
use App\Models\SeoProject;
use App\Models\SeoSitemapEntry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SeoSitemapAnalyzerPageTest extends TestCase
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
            ->test(SeoSitemapAnalyzer::class)
            ->assertSee('برای دیدن تحلیل Sitemap، ابتدا یک پروژه انتخاب کن.');
    }

    public function test_it_points_to_the_project_edit_page_when_there_is_no_sitemap_data_yet(): void
    {
        $project = SeoProject::factory()->create();

        Livewire::actingAs($this->admin())
            ->test(SeoSitemapAnalyzer::class, ['projectId' => $project->id])
            ->assertSee('هنوز هیچ sitemap برای این پروژه همگام‌سازی نشده است.')
            ->assertSeeHtml('/edit');
    }

    public function test_it_shows_the_overview_and_table_for_a_project_with_sitemap_data(): void
    {
        $project = SeoProject::factory()->create();
        SeoSitemapEntry::factory()->create([
            'seo_project_id' => $project->id,
            'url' => 'https://ganjnameh.example.com/telecabin',
            'title' => 'تله‌کابین گنجنامه',
        ]);

        Livewire::actingAs($this->admin())
            ->test(SeoSitemapAnalyzer::class, ['projectId' => $project->id])
            ->assertSee('کل صفحات')
            ->assertSee('تله‌کابین گنجنامه');
    }

    public function test_a_user_without_permission_is_forbidden(): void
    {
        $user = User::factory()->create();
        $user->assignRole(Role::create(['name' => 'بدون دسترسی تحلیل‌گر Sitemap']));

        Livewire::actingAs($user)
            ->test(SeoSitemapAnalyzer::class)
            ->assertForbidden();
    }
}
