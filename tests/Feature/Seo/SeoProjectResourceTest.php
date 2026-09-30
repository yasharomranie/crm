<?php

namespace Tests\Feature\Seo;

use App\Enums\SeoProjectGoal;
use App\Enums\SeoSearchType;
use App\Filament\Resources\SeoProjects\Pages\CreateSeoProject;
use App\Filament\Resources\SeoProjects\Pages\EditSeoProject;
use App\Filament\Resources\SeoProjects\Pages\ListSeoProjects;
use App\Jobs\DiscoverSeoKeywordsJob;
use App\Jobs\SyncSitemapDataJob;
use App\Models\SeoProject;
use App\Models\SeoSearchTypeMetric;
use App\Models\SeoSitemapEntry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SeoProjectResourceTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $admin = User::factory()->create();
        $admin->assignRole(Role::firstOrCreate(['name' => 'admin']));

        return $admin;
    }

    public function test_the_index_page_lists_seo_projects(): void
    {
        $project = SeoProject::factory()->create();

        Livewire::actingAs($this->admin())
            ->test(ListSeoProjects::class)
            ->assertCanSeeTableRecords([$project]);
    }

    public function test_a_project_can_be_created_with_competitors(): void
    {
        Livewire::actingAs($this->admin())
            ->test(CreateSeoProject::class)
            ->fillForm([
                'name' => 'گنجنامه - سایت اصلی',
                'website_url' => 'https://ganjnameh.example.com',
                'goal' => SeoProjectGoal::Booking->value,
                'market' => 'ایران',
                'competitors' => [
                    ['domain' => 'competitor-one.example.com'],
                    ['domain' => 'competitor-two.example.com'],
                ],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $project = SeoProject::where('name', 'گنجنامه - سایت اصلی')->firstOrFail();

        $this->assertSame(SeoProjectGoal::Booking, $project->goal);
        $this->assertCount(2, $project->competitors);
    }

    public function test_excluded_query_terms_are_saved_and_parsed_into_a_list(): void
    {
        $project = SeoProject::factory()->create();

        Livewire::actingAs($this->admin())
            ->test(EditSeoProject::class, ['record' => $project->getRouteKey()])
            ->fillForm(['excluded_query_terms' => "گنجنامه\nهمدان, تله کابین"])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame(
            ['گنجنامه', 'همدان', 'تله کابین'],
            $project->refresh()->excludedQueryTermsList(),
        );
    }

    public function test_the_search_type_traffic_section_shows_recent_click_totals(): void
    {
        $project = SeoProject::factory()->create();
        SeoSearchTypeMetric::factory()->create([
            'seo_project_id' => $project->id,
            'search_type' => SeoSearchType::Image->value,
            'date' => now()->subDays(5),
            'clicks' => 17,
        ]);
        // Outside the 28-day window - must not be counted.
        SeoSearchTypeMetric::factory()->create([
            'seo_project_id' => $project->id,
            'search_type' => SeoSearchType::Image->value,
            'date' => now()->subDays(60),
            'clicks' => 999,
        ]);

        Livewire::actingAs($this->admin())
            ->test(EditSeoProject::class, ['record' => $project->getRouteKey()])
            ->assertSee('جستجوی تصویر')
            ->assertSee('17 کلیک');
    }

    public function test_the_search_type_traffic_section_is_hidden_when_there_is_no_data(): void
    {
        $project = SeoProject::factory()->create();

        Livewire::actingAs($this->admin())
            ->test(EditSeoProject::class, ['record' => $project->getRouteKey()])
            ->assertDontSee('ترافیک بر اساس نوع جستجو');
    }

    public function test_the_sitemap_section_shows_recently_added_and_edited_pages_and_monthly_volume(): void
    {
        $project = SeoProject::factory()->create();

        $addedAt = now();
        SeoSitemapEntry::factory()->create([
            'seo_project_id' => $project->id,
            'url' => 'https://ganjnameh.example.com/telecabin',
            'title' => 'تله‌کابین گنجنامه',
            'first_seen_at' => $addedAt,
            'last_seen_at' => $addedAt,
            'last_changed_at' => $addedAt,
        ]);

        $editedAt = now();
        SeoSitemapEntry::factory()->create([
            'seo_project_id' => $project->id,
            'url' => 'https://ganjnameh.example.com/about',
            'title' => 'درباره ما',
            'first_seen_at' => now()->subDays(40),
            'last_seen_at' => $editedAt,
            'last_changed_at' => $editedAt,
        ]);

        // خارج از این ماه - نباید در حجم محتوای این ماه شمرده شود.
        SeoSitemapEntry::factory()->create([
            'seo_project_id' => $project->id,
            'first_seen_at' => now()->subMonths(2),
            'last_seen_at' => now()->subMonths(2),
            'last_changed_at' => now()->subMonths(2),
        ]);

        Livewire::actingAs($this->admin())
            ->test(EditSeoProject::class, ['record' => $project->getRouteKey()])
            ->assertSee('پایش Sitemap')
            ->assertSee('تله‌کابین گنجنامه')
            ->assertSee('درباره ما')
            ->assertSee('1 صفحه');
    }

    public function test_the_sitemap_section_is_hidden_when_there_are_no_entries(): void
    {
        $project = SeoProject::factory()->create();

        Livewire::actingAs($this->admin())
            ->test(EditSeoProject::class, ['record' => $project->getRouteKey()])
            ->assertDontSee('پایش Sitemap');
    }

    public function test_the_sync_sitemap_now_action_is_only_visible_when_a_sitemap_url_is_registered(): void
    {
        $withSitemap = SeoProject::factory()->create(['sitemap_url' => 'https://ganjnameh.example.com/sitemap.xml']);
        $withoutSitemap = SeoProject::factory()->create(['sitemap_url' => null]);

        Livewire::actingAs($this->admin())
            ->test(EditSeoProject::class, ['record' => $withSitemap->getRouteKey()])
            ->assertActionVisible('syncSitemapNow');

        Livewire::actingAs($this->admin())
            ->test(EditSeoProject::class, ['record' => $withoutSitemap->getRouteKey()])
            ->assertActionHidden('syncSitemapNow');
    }

    public function test_the_sync_sitemap_now_action_dispatches_the_job(): void
    {
        Queue::fake();
        $project = SeoProject::factory()->create(['sitemap_url' => 'https://ganjnameh.example.com/sitemap.xml']);

        Livewire::actingAs($this->admin())
            ->test(EditSeoProject::class, ['record' => $project->getRouteKey()])
            ->callAction('syncSitemapNow');

        Queue::assertPushed(SyncSitemapDataJob::class, fn ($job) => $job->seoProjectId === $project->id);
    }

    public function test_discovering_keywords_warns_when_real_services_is_empty(): void
    {
        Queue::fake();
        $project = SeoProject::factory()->create(['real_services' => null]);

        Livewire::actingAs($this->admin())
            ->test(EditSeoProject::class, ['record' => $project->getRouteKey()])
            ->callAction('discoverKeywords', data: ['count' => 10])
            ->assertNotified('فیلد «خدمات و محصولات واقعی» این پروژه خالی است.');

        Queue::assertPushed(DiscoverSeoKeywordsJob::class);
    }

    public function test_discovering_keywords_does_not_warn_when_real_services_is_filled(): void
    {
        Queue::fake();
        $project = SeoProject::factory()->create(['real_services' => 'داریم: تله‌کابین']);

        Livewire::actingAs($this->admin())
            ->test(EditSeoProject::class, ['record' => $project->getRouteKey()])
            ->callAction('discoverKeywords', data: ['count' => 10])
            ->assertNotNotified('فیلد «خدمات و محصولات واقعی» این پروژه خالی است.');
    }
}
