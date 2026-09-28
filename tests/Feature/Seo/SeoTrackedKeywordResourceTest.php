<?php

namespace Tests\Feature\Seo;

use App\Filament\Resources\SeoTrackedKeywords\Pages\CreateSeoTrackedKeyword;
use App\Filament\Resources\SeoTrackedKeywords\Pages\EditSeoTrackedKeyword;
use App\Filament\Resources\SeoTrackedKeywords\Pages\ListSeoTrackedKeywords;
use App\Jobs\CheckKeywordRankJob;
use App\Models\SeoProject;
use App\Models\SeoTrackedKeyword;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SeoTrackedKeywordResourceTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $admin = User::factory()->create();
        $admin->assignRole(Role::firstOrCreate(['name' => 'admin']));

        return $admin;
    }

    public function test_the_index_page_lists_tracked_keywords(): void
    {
        $keyword = SeoTrackedKeyword::factory()->create();

        Livewire::actingAs($this->admin())
            ->test(ListSeoTrackedKeywords::class)
            ->assertCanSeeTableRecords([$keyword]);
    }

    public function test_creating_a_tracked_keyword(): void
    {
        $project = SeoProject::factory()->create();

        Livewire::actingAs($this->admin())
            ->test(CreateSeoTrackedKeyword::class)
            ->fillForm([
                'seo_project_id' => $project->id,
                'keyword' => 'تور کیش',
                'is_active' => true,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $keyword = SeoTrackedKeyword::firstOrFail();
        $this->assertSame($project->id, $keyword->seo_project_id);
        $this->assertSame('تور کیش', $keyword->keyword);
        $this->assertTrue($keyword->is_active);
    }

    public function test_creating_an_active_keyword_beyond_the_fifty_cap_is_halted(): void
    {
        $project = SeoProject::factory()->create();
        SeoTrackedKeyword::factory()->count(50)->create(['seo_project_id' => $project->id, 'is_active' => true]);

        Livewire::actingAs($this->admin())
            ->test(CreateSeoTrackedKeyword::class)
            ->fillForm([
                'seo_project_id' => $project->id,
                'keyword' => 'کلمه پنجاه و یکم',
                'is_active' => true,
            ])
            ->call('create');

        $this->assertSame(50, SeoTrackedKeyword::where('seo_project_id', $project->id)->count());
        $this->assertDatabaseMissing(SeoTrackedKeyword::class, [
            'seo_project_id' => $project->id,
            'keyword' => 'کلمه پنجاه و یکم',
        ]);
    }

    public function test_creating_an_inactive_keyword_is_not_limited_by_the_cap(): void
    {
        $project = SeoProject::factory()->create();
        SeoTrackedKeyword::factory()->count(50)->create(['seo_project_id' => $project->id, 'is_active' => true]);

        Livewire::actingAs($this->admin())
            ->test(CreateSeoTrackedKeyword::class)
            ->fillForm([
                'seo_project_id' => $project->id,
                'keyword' => 'کلمه غیرفعال',
                'is_active' => false,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas(SeoTrackedKeyword::class, [
            'seo_project_id' => $project->id,
            'keyword' => 'کلمه غیرفعال',
            'is_active' => false,
        ]);
    }

    public function test_editing_a_tracked_keyword(): void
    {
        $keyword = SeoTrackedKeyword::factory()->create(['keyword' => 'قدیمی']);

        Livewire::actingAs($this->admin())
            ->test(EditSeoTrackedKeyword::class, ['record' => $keyword->getRouteKey()])
            ->fillForm(['keyword' => 'جدید'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('جدید', $keyword->fresh()->keyword);
    }

    public function test_reactivating_a_keyword_beyond_the_fifty_cap_is_halted(): void
    {
        $project = SeoProject::factory()->create();
        SeoTrackedKeyword::factory()->count(50)->create(['seo_project_id' => $project->id, 'is_active' => true]);
        $inactive = SeoTrackedKeyword::factory()->create(['seo_project_id' => $project->id, 'is_active' => false]);

        Livewire::actingAs($this->admin())
            ->test(EditSeoTrackedKeyword::class, ['record' => $inactive->getRouteKey()])
            ->fillForm(['is_active' => true])
            ->call('save');

        $this->assertFalse($inactive->fresh()->is_active);
    }

    public function test_moving_an_active_keyword_into_a_full_project_is_halted(): void
    {
        $fullProject = SeoProject::factory()->create();
        SeoTrackedKeyword::factory()->count(50)->create(['seo_project_id' => $fullProject->id, 'is_active' => true]);

        $otherProject = SeoProject::factory()->create();
        $keyword = SeoTrackedKeyword::factory()->create(['seo_project_id' => $otherProject->id, 'is_active' => true]);

        Livewire::actingAs($this->admin())
            ->test(EditSeoTrackedKeyword::class, ['record' => $keyword->getRouteKey()])
            ->fillForm(['seo_project_id' => $fullProject->id, 'is_active' => true])
            ->call('save');

        $this->assertSame($otherProject->id, $keyword->fresh()->seo_project_id);
    }

    public function test_check_now_dispatches_the_rank_check_job(): void
    {
        Bus::fake();

        $keyword = SeoTrackedKeyword::factory()->create();

        Livewire::actingAs($this->admin())
            ->test(ListSeoTrackedKeywords::class)
            ->callTableAction('checkNow', $keyword);

        Bus::assertDispatched(CheckKeywordRankJob::class, fn (CheckKeywordRankJob $job) => $job->seoTrackedKeywordId === $keyword->id);
    }

    public function test_view_history_action_is_available_for_a_tracked_keyword(): void
    {
        $keyword = SeoTrackedKeyword::factory()->create();

        Livewire::actingAs($this->admin())
            ->test(ListSeoTrackedKeywords::class)
            ->assertTableActionVisible('viewHistory', $keyword);
    }
}
