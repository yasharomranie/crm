<?php

namespace Tests\Feature\Seo;

use App\Enums\SeoOpportunityOutcomeVerdict;
use App\Enums\SeoOpportunityStatus;
use App\Enums\SeoOpportunityType;
use App\Filament\Resources\SeoOpportunities\Pages\ManageSeoOpportunities;
use App\Models\SeoContentItem;
use App\Models\SeoOpportunity;
use App\Models\SeoOpportunityOutcome;
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

    public function test_the_subject_column_shows_a_decoded_page_url_or_the_query(): void
    {
        SeoOpportunity::factory()->create([
            'subject_page_url' => 'https://ganjnameh.example.com/%D8%AA%D9%84%D9%87-%DA%A9%D8%A7%D8%A8%DB%8C%D9%86',
            'subject_query' => null,
        ]);
        SeoOpportunity::factory()->create([
            'subject_page_url' => null,
            'subject_query' => 'تله کابین گنجنامه',
        ]);

        Livewire::actingAs($this->admin())
            ->test(ManageSeoOpportunities::class)
            ->assertSee('https://ganjnameh.example.com/تله-کابین')
            ->assertSee('تله کابین گنجنامه');
    }

    public function test_recording_a_primary_page_decision_saves_it_as_resolution_notes(): void
    {
        $pageA = 'https://ganjnameh.example.com/villas';
        $pageB = 'https://ganjnameh.example.com/villas-booking';
        $opportunity = SeoOpportunity::factory()->create([
            'type' => SeoOpportunityType::Cannibalization,
            'evidence' => [
                'keyword' => 'رزرو ویلا گنجنامه',
                'pages' => [
                    ['page_url' => $pageA, 'clicks' => 30, 'impressions' => 400, 'share' => 0.6],
                    ['page_url' => $pageB, 'clicks' => 20, 'impressions' => 300, 'share' => 0.4],
                ],
            ],
        ]);

        Livewire::actingAs($this->admin())
            ->test(ManageSeoOpportunities::class)
            ->callTableAction('resolveCannibalization', $opportunity, data: [
                'primary_page_url' => $pageA,
                'action_type' => 'redirect_301',
                'notes' => 'صفحه‌ی دوم ترافیک کمتری دارد.',
            ])
            ->assertHasNoTableActionErrors();

        $fresh = $opportunity->fresh();
        $this->assertStringContainsString($pageA, $fresh->resolution_notes);
        $this->assertStringContainsString('ریدایرکت ۳۰۱', $fresh->resolution_notes);
        $this->assertStringContainsString('صفحه‌ی دوم ترافیک کمتری دارد.', $fresh->resolution_notes);

        // Re-detection must never wipe it - resolution_notes lives outside
        // the `evidence` column OpportunityEngine overwrites wholesale.
        $fresh->update(['evidence' => ['keyword' => 'رزرو ویلا گنجنامه', 'pages' => []]]);
        $this->assertNotNull($fresh->fresh()->resolution_notes);
    }

    public function test_the_resolve_cannibalization_action_is_hidden_for_other_opportunity_types(): void
    {
        $opportunity = SeoOpportunity::factory()->create(['type' => SeoOpportunityType::CtrOpportunity]);

        Livewire::actingAs($this->admin())
            ->test(ManageSeoOpportunities::class)
            ->assertTableActionHidden('resolveCannibalization', $opportunity);
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

    public function test_marking_an_opportunity_resolved_captures_an_outcome_baseline(): void
    {
        $opportunity = SeoOpportunity::factory()->create(['status' => SeoOpportunityStatus::Detected]);

        Livewire::actingAs($this->admin())
            ->test(ManageSeoOpportunities::class)
            ->callTableAction('markResolved', $opportunity);

        $outcome = $opportunity->fresh()->outcome;
        $this->assertNotNull($outcome);
        $this->assertSame(SeoOpportunityOutcomeVerdict::Pending, $outcome->verdict);
        $this->assertNull($outcome->followup_measured_at);
    }

    public function test_the_view_outcome_action_is_only_available_once_an_outcome_exists(): void
    {
        $withoutOutcome = SeoOpportunity::factory()->create();
        $withOutcome = SeoOpportunity::factory()->create();
        SeoOpportunityOutcome::factory()->create(['seo_opportunity_id' => $withOutcome->id]);

        Livewire::actingAs($this->admin())
            ->test(ManageSeoOpportunities::class)
            ->assertTableActionHidden('viewOutcome', $withoutOutcome)
            ->assertTableActionVisible('viewOutcome', $withOutcome);
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
