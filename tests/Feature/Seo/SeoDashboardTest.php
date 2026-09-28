<?php

namespace Tests\Feature\Seo;

use App\Enums\SeoContentItemStatus;
use App\Enums\SeoKeywordSuggestionStatus;
use App\Enums\SeoOpportunityOutcomeVerdict;
use App\Enums\SeoOpportunityStatus;
use App\Filament\Widgets\SeoOverviewStatsWidget;
use App\Filament\Widgets\SeoUpcomingContentWidget;
use App\Models\SeoContentItem;
use App\Models\SeoKeywordProfile;
use App\Models\SeoKeywordSuggestion;
use App\Models\SeoOpportunity;
use App\Models\SeoOpportunityOutcome;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * "داشبورد سئو" is its own report row, deliberately scoped there by
 * App\Filament\Pages\Dashboard excluding these widgets from its own
 * list - mirrors SalesPipelineDashboardTest's structure for the same
 * kind of page.
 */
class SeoDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_widgets_render_with_real_seo_data(): void
    {
        SeoOpportunity::factory()->create(['status' => SeoOpportunityStatus::Detected]);
        SeoKeywordProfile::factory()->create();
        SeoKeywordSuggestion::factory()->create(['status' => SeoKeywordSuggestionStatus::New]);
        SeoContentItem::factory()->create(['due_date' => now()->addDays(2)]);

        $this->actingAs(User::factory()->create());

        Livewire::test(SeoOverviewStatsWidget::class)->assertOk();
        Livewire::test(SeoUpcomingContentWidget::class)->assertOk();
    }

    /**
     * Regression guard matching SalesPipelineDashboardTest's own: a widget
     * mounted directly via Livewire::test() never hydrates (that only
     * happens on a *second* request against an already-mounted
     * component), so this forces that round trip.
     */
    public function test_the_widgets_survive_a_second_hydration_not_just_the_first_mount(): void
    {
        $this->actingAs(User::factory()->create());

        Livewire::test(SeoOverviewStatsWidget::class)->call('$refresh')->assertOk();
        Livewire::test(SeoUpcomingContentWidget::class)->call('$refresh')->assertOk();
    }

    public function test_the_widgets_render_fine_with_no_seo_data_at_all(): void
    {
        $this->actingAs(User::factory()->create());

        Livewire::test(SeoOverviewStatsWidget::class)->assertOk();
        Livewire::test(SeoUpcomingContentWidget::class)->assertOk()->assertSee('محتوایی نزدیک به مهلت نیست');
    }

    public function test_the_seo_dashboard_page_shows_the_widgets(): void
    {
        $response = $this->actingAs(User::factory()->create(['is_active' => true]))
            ->get(route('filament.admin.pages.seo-dashboard'))
            ->assertOk();

        $response->assertSeeLivewire(SeoOverviewStatsWidget::class);
        $response->assertSeeLivewire(SeoUpcomingContentWidget::class);
    }

    public function test_the_widgets_are_not_visible_on_the_main_dashboard(): void
    {
        $response = $this->actingAs(User::factory()->create(['is_active' => true]))
            ->get('/admin')
            ->assertOk();

        $response->assertDontSeeLivewire(SeoOverviewStatsWidget::class);
        $response->assertDontSeeLivewire(SeoUpcomingContentWidget::class);
    }

    public function test_the_upcoming_content_widget_excludes_published_and_far_off_items(): void
    {
        $due = SeoContentItem::factory()->create(['status' => SeoContentItemStatus::Writing, 'due_date' => now()->addDays(2)]);
        $overdue = SeoContentItem::factory()->create(['status' => SeoContentItemStatus::Planned, 'due_date' => now()->subDays(3)]);
        $published = SeoContentItem::factory()->create(['status' => SeoContentItemStatus::Published, 'due_date' => now()->addDay()]);
        $farOff = SeoContentItem::factory()->create(['status' => SeoContentItemStatus::Idea, 'due_date' => now()->addDays(30)]);

        $this->actingAs(User::factory()->create());

        Livewire::test(SeoUpcomingContentWidget::class)
            ->assertCanSeeTableRecords([$due, $overdue])
            ->assertCanNotSeeTableRecords([$published, $farOff]);
    }

    public function test_the_success_rate_stat_only_counts_evaluated_outcomes(): void
    {
        SeoOpportunityOutcome::factory()->create([
            'seo_opportunity_id' => SeoOpportunity::factory(),
            'followup_measured_at' => now(),
            'verdict' => SeoOpportunityOutcomeVerdict::Improved,
        ]);
        SeoOpportunityOutcome::factory()->create([
            'seo_opportunity_id' => SeoOpportunity::factory(),
            'followup_measured_at' => now(),
            'verdict' => SeoOpportunityOutcomeVerdict::Declined,
        ]);
        // Still pending - must not count toward the rate.
        SeoOpportunityOutcome::factory()->create([
            'seo_opportunity_id' => SeoOpportunity::factory(),
            'followup_measured_at' => null,
            'verdict' => SeoOpportunityOutcomeVerdict::Pending,
        ]);

        $this->actingAs(User::factory()->create());

        Livewire::test(SeoOverviewStatsWidget::class)
            ->assertOk()
            ->assertSee('۱ بهبود از ۲ اقدام ارزیابی‌شده');
    }

    public function test_a_user_without_the_seo_dashboard_permission_is_denied(): void
    {
        $user = User::factory()->create(['is_active' => true]);
        $user->assignRole(Role::create(['name' => 'بدون دسترسی داشبورد سئو']));

        $this->actingAs($user)
            ->get(route('filament.admin.pages.seo-dashboard'))
            ->assertForbidden();
    }
}
