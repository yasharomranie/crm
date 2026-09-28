<?php

namespace Tests\Feature\Seo;

use App\Enums\SeoOpportunityStatus;
use App\Filament\Resources\SeoOpportunities\Pages\ManageSeoOpportunities;
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
}
