<?php

namespace Tests\Feature\Seo;

use App\Enums\SeoOpportunityStatus;
use App\Filament\Resources\SeoOpportunities\Pages\ManageSeoOpportunities;
use App\Models\SeoContentItem;
use App\Models\SeoOpportunity;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SeoOpportunityResourceTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $admin = User::factory()->create();
        $admin->assignRole(Role::firstOrCreate(['name' => 'admin']));

        return $admin;
    }

    public function test_the_index_page_lists_opportunities(): void
    {
        $opportunity = SeoOpportunity::factory()->create();

        Livewire::actingAs($this->admin())
            ->test(ManageSeoOpportunities::class)
            ->assertCanSeeTableRecords([$opportunity]);
    }

    public function test_marking_an_opportunity_reviewed_updates_its_status(): void
    {
        $opportunity = SeoOpportunity::factory()->create(['status' => SeoOpportunityStatus::Detected]);

        Livewire::actingAs($this->admin())
            ->test(ManageSeoOpportunities::class)
            ->callTableAction('markReviewed', $opportunity);

        $this->assertSame(SeoOpportunityStatus::Reviewed, $opportunity->fresh()->status);
    }

    public function test_marking_an_opportunity_resolved_sets_resolved_at(): void
    {
        $opportunity = SeoOpportunity::factory()->create(['status' => SeoOpportunityStatus::Detected]);

        Livewire::actingAs($this->admin())
            ->test(ManageSeoOpportunities::class)
            ->callTableAction('markResolved', $opportunity);

        $fresh = $opportunity->fresh();
        $this->assertSame(SeoOpportunityStatus::Resolved, $fresh->status);
        $this->assertNotNull($fresh->resolved_at);
    }

    public function test_dismissing_an_opportunity_updates_its_status(): void
    {
        $opportunity = SeoOpportunity::factory()->create(['status' => SeoOpportunityStatus::Detected]);

        Livewire::actingAs($this->admin())
            ->test(ManageSeoOpportunities::class)
            ->callTableAction('dismiss', $opportunity);

        $this->assertSame(SeoOpportunityStatus::Dismissed, $opportunity->fresh()->status);
    }

    public function test_planning_content_for_an_opportunity_creates_a_linked_content_item(): void
    {
        $opportunity = SeoOpportunity::factory()->create(['subject_query' => 'تله کابین گنجنامه قیمت']);

        Livewire::actingAs($this->admin())
            ->test(ManageSeoOpportunities::class)
            ->callTableAction('planContent', $opportunity, data: [
                'title' => 'مقاله راهنمای قیمت تله کابین',
                'content_type' => 'مقاله وبلاگ',
            ]);

        $item = SeoContentItem::firstOrFail();
        $this->assertSame($opportunity->id, $item->seo_opportunity_id);
        $this->assertSame($opportunity->seo_project_id, $item->seo_project_id);
        $this->assertSame('مقاله راهنمای قیمت تله کابین', $item->title);
        $this->assertSame('تله کابین گنجنامه قیمت', $item->target_keyword);
    }
}
