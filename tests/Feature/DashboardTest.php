<?php

namespace Tests\Feature;

use App\Enums\DealStage;
use App\Filament\Pages\Dashboard;
use App\Filament\Widgets\BookingStatusChart;
use App\Filament\Widgets\GanjnamehStatsOverview;
use App\Filament\Widgets\RevenueTrendChart;
use App\Models\AttendanceRecord;
use App\Models\Booking;
use App\Models\Deal;
use App\Models\Room;
use App\Models\Task;
use App\Models\User;
use Filament\Facades\Filament;
use Filament\Widgets\AccountWidget;
use Filament\Widgets\FilamentInfoWidget;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_default_placeholder_widgets_are_no_longer_registered(): void
    {
        $widgets = Filament::getPanel('admin')->getWidgets();

        $this->assertNotContains(AccountWidget::class, $widgets);
        $this->assertNotContains(FilamentInfoWidget::class, $widgets);
    }

    public function test_the_custom_dashboard_widgets_are_discovered(): void
    {
        $widgets = Filament::getPanel('admin')->getWidgets();

        $this->assertContains(GanjnamehStatsOverview::class, $widgets);
        $this->assertContains(RevenueTrendChart::class, $widgets);
        $this->assertContains(BookingStatusChart::class, $widgets);
    }

    public function test_the_dashboard_page_loads_for_an_authenticated_user(): void
    {
        // is_active explicitly true: a freshly-built factory instance never
        // reflects the users.is_active column's own DB default until
        // refreshed, and actingAs() authenticates that in-memory instance
        // as-is rather than re-fetching it - only relevant to this direct
        // test setup, not to a real login (which always re-hydrates from
        // the database and picks up the column default correctly).
        $this->actingAs(User::factory()->create(['is_active' => true]))
            ->get('/admin')
            ->assertOk();
    }

    public function test_the_stats_overview_widget_renders_with_real_data(): void
    {
        Room::factory()->create(['is_active' => true]);
        Booking::factory()->create();
        Deal::create(['title' => 'قرارداد نمونه', 'stage' => DealStage::Negotiation, 'value' => 5_000_000]);
        Task::factory()->create();
        AttendanceRecord::factory()->create(['date' => today()]);

        $this->actingAs(User::factory()->create());

        Livewire::test(GanjnamehStatsOverview::class)->assertOk();
    }

    public function test_the_chart_widgets_render_with_real_data(): void
    {
        Booking::factory()->create();

        $this->actingAs(User::factory()->create());

        Livewire::test(RevenueTrendChart::class)->assertOk();
        Livewire::test(BookingStatusChart::class)->assertOk();
    }

    public function test_the_process_queued_jobs_action_is_visible_and_confirms_on_click(): void
    {
        $this->actingAs(User::factory()->create(['is_active' => true]));

        Livewire::test(Dashboard::class)
            ->assertActionVisible('processQueuedJobs')
            ->callAction('processQueuedJobs')
            ->assertNotified();
    }
}
