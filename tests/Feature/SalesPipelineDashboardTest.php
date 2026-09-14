<?php

namespace Tests\Feature;

use App\Enums\DealSource;
use App\Enums\DealStage;
use App\Filament\Widgets\DealSourceChart;
use App\Filament\Widgets\DealWinLossTrendChart;
use App\Filament\Widgets\SalesFunnelChart;
use App\Filament\Widgets\SalesPipelineStatsOverview;
use App\Filament\Widgets\StaleDealsWidget;
use App\Filament\Widgets\TopSalesRepsWidget;
use App\Models\Deal;
use App\Models\Guest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * The sales pipeline page grew a full report row on top of its kanban board
 * (KPIs, stage distribution, source breakdown, win/loss trend, a
 * leaderboard, and a stale-deals list). These widgets are deliberately
 * scoped to that one page via OnlyVisibleOnSalesPipeline - never on the
 * main dashboard, which already has its own, simpler pipeline stat.
 */
class SalesPipelineDashboardTest extends TestCase
{
    use RefreshDatabase;

    private function seedRepresentativeDeals(): void
    {
        $rep = User::factory()->create(['name' => 'کارشناس نمونه']);
        $guest = Guest::factory()->create();

        Deal::create([
            'title' => 'سرنخ تازه', 'stage' => DealStage::NewLead, 'value' => 2_000_000,
            'source' => DealSource::Online, 'guest_id' => $guest->id,
        ]);
        Deal::create([
            'title' => 'در حال مذاکره', 'stage' => DealStage::Negotiation, 'value' => 8_000_000,
            'source' => DealSource::Referral, 'assigned_to' => $rep->id,
        ]);
        Deal::create([
            'title' => 'معاملهٔ بردهٔ قدیمی', 'stage' => DealStage::Won, 'value' => 15_000_000,
            'source' => DealSource::Phone, 'assigned_to' => $rep->id,
        ]);
        Deal::create([
            'title' => 'معاملهٔ ازدست‌رفته', 'stage' => DealStage::Lost, 'value' => 3_000_000,
            'source' => DealSource::WalkIn,
        ]);
    }

    public function test_the_report_widgets_render_with_real_pipeline_data(): void
    {
        $this->seedRepresentativeDeals();

        $this->actingAs(User::factory()->create());

        Livewire::test(SalesPipelineStatsOverview::class)->assertOk();
        Livewire::test(SalesFunnelChart::class)->assertOk();
        Livewire::test(DealSourceChart::class)->assertOk();
        Livewire::test(DealWinLossTrendChart::class)->assertOk();
        Livewire::test(TopSalesRepsWidget::class)->assertOk();
        Livewire::test(StaleDealsWidget::class)->assertOk();
    }

    public function test_the_report_widgets_render_fine_with_no_deals_at_all(): void
    {
        $this->actingAs(User::factory()->create());

        Livewire::test(SalesPipelineStatsOverview::class)->assertOk();
        Livewire::test(TopSalesRepsWidget::class)->assertOk()->assertSee('هنوز معامله‌ای به سرانجام');
    }

    public function test_the_sales_pipeline_page_shows_the_report_widgets(): void
    {
        $this->seedRepresentativeDeals();

        // Widgets are lazy-loaded (Filament's CanBeLazy default), so a plain
        // GET only ever renders their placeholders - assertSeeLivewire is
        // the correct check that each report widget is actually wired onto
        // this page, not the main dashboard.
        $response = $this->actingAs(User::factory()->create(['is_active' => true]))
            ->get(route('filament.admin.pages.sales-pipeline'))
            ->assertOk();

        $response->assertSeeLivewire(SalesPipelineStatsOverview::class);
        $response->assertSeeLivewire(SalesFunnelChart::class);
        $response->assertSeeLivewire(DealSourceChart::class);
        $response->assertSeeLivewire(DealWinLossTrendChart::class);
        $response->assertSeeLivewire(TopSalesRepsWidget::class);
        $response->assertSeeLivewire(StaleDealsWidget::class);
    }

    public function test_the_report_widgets_are_not_visible_on_the_main_dashboard(): void
    {
        $this->seedRepresentativeDeals();

        $response = $this->actingAs(User::factory()->create(['is_active' => true]))
            ->get('/admin')
            ->assertOk();

        $response->assertDontSeeLivewire(SalesPipelineStatsOverview::class);
        $response->assertDontSeeLivewire(SalesFunnelChart::class);
        $response->assertDontSeeLivewire(DealSourceChart::class);
        $response->assertDontSeeLivewire(DealWinLossTrendChart::class);
        $response->assertDontSeeLivewire(TopSalesRepsWidget::class);
        $response->assertDontSeeLivewire(StaleDealsWidget::class);
    }

    public function test_the_stale_deals_widget_only_lists_open_deals_untouched_for_two_weeks(): void
    {
        $stale = Deal::create(['title' => 'معاملهٔ راکد', 'stage' => DealStage::ProposalSent, 'value' => 4_000_000]);
        $stale->timestamps = false;
        $stale->updated_at = now()->subDays(20);
        $stale->save();

        $fresh = Deal::create(['title' => 'معاملهٔ تازه', 'stage' => DealStage::ProposalSent, 'value' => 1_000_000]);

        $this->actingAs(User::factory()->create());

        Livewire::test(StaleDealsWidget::class)
            ->assertCanSeeTableRecords([$stale])
            ->assertCanNotSeeTableRecords([$fresh]);
    }

    public function test_a_user_without_the_sales_pipeline_permission_is_denied(): void
    {
        $user = User::factory()->create(['is_active' => true]);
        $user->assignRole(Role::create(['name' => 'بدون دسترسی پایپ‌لاین']));

        $this->actingAs($user)
            ->get(route('filament.admin.pages.sales-pipeline'))
            ->assertForbidden();
    }
}
