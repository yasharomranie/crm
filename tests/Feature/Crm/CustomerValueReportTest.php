<?php

namespace Tests\Feature\Crm;

use App\Domain\Crm\CustomerValueReport;
use App\Enums\BookingStatus;
use App\Enums\LoyaltyTier;
use App\Enums\TaskType;
use App\Filament\Pages\CustomerValueReportPage;
use App\Models\Booking;
use App\Models\BookingCharge;
use App\Models\Guest;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class CustomerValueReportTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $admin = User::factory()->create();
        $admin->assignRole(Role::firstOrCreate(['name' => 'admin']));

        return $admin;
    }

    private function stay(Guest $guest, int $daysAgo, int $amount, BookingStatus $status = BookingStatus::CheckedOut): Booking
    {
        $booking = Booking::factory()->create([
            'guest_id' => $guest->id,
            'status' => $status,
            'actual_check_out_at' => $status === BookingStatus::CheckedOut ? now()->subDays($daysAgo) : null,
        ]);
        $booking->charges()->delete();
        BookingCharge::query()->create(['booking_id' => $booking->id, 'title' => 'اقامت', 'quantity' => 1, 'unit_amount' => $amount]);

        return $booking;
    }

    public function test_status_follows_the_guests_own_rhythm(): void
    {
        $report = new CustomerValueReport;

        $this->assertSame('active', $report->statusFor(30, 60));
        $this->assertSame('active', $report->statusFor(80, 60)); // 80 <= 1.5 * 60
        $this->assertSame('at_risk', $report->statusFor(100, 60));
        $this->assertSame('active', $report->statusFor(50, 10)); // under the 60-day floor
        $this->assertSame('at_risk', $report->statusFor(61, 10));
        $this->assertSame('active', $report->statusFor(200, null)); // one stay: no rhythm
        $this->assertSame('lost', $report->statusFor(365, 30));
        $this->assertSame('lost', $report->statusFor(400, null));
    }

    public function test_value_counts_only_completed_stays(): void
    {
        $guest = Guest::factory()->create();
        $this->stay($guest, 10, 1_000_000);
        $this->stay($guest, 400, 2_000_000);
        $this->stay($guest, 0, 5_000_000, BookingStatus::Confirmed);

        $stats = (new CustomerValueReport)->guestStats()->sole();

        $this->assertSame(2, $stats['stays']);
        $this->assertSame(3_000_000, $stats['spend']);
    }

    public function test_the_usual_gap_is_the_average_time_between_stays(): void
    {
        $guest = Guest::factory()->create();
        $this->stay($guest, 300, 100);
        $this->stay($guest, 200, 100);
        $this->stay($guest, 100, 100);

        $this->assertSame(100, (new CustomerValueReport)->guestStats()->sole()['usual_gap_days']);
    }

    public function test_the_summary_adds_up(): void
    {
        $loyal = Guest::factory()->create(['name' => 'وفادار']);
        $this->stay($loyal, 300, 60_000_000);
        $this->stay($loyal, 200, 60_000_000);
        $this->stay($loyal, 100, 60_000_000); // gap 100d, last 100d ago: active
        $drifting = Guest::factory()->create(['name' => 'در حال دور شدن']);
        $this->stay($drifting, 340, 1_000_000);
        $this->stay($drifting, 300, 1_000_000); // gap 40d, last 300d ago: at risk (not yet lost)
        $lost = Guest::factory()->create(['name' => 'از دست رفته']);
        $this->stay($lost, 500, 2_000_000);
        Guest::factory()->create(['name' => 'بدون اقامت']);

        $report = (new CustomerValueReport)->build();

        $this->assertSame(3, $report['customers']);
        $this->assertSame(184_000_000, $report['revenue']);
        $this->assertSame(1, $report['active']);
        $this->assertSame(1, $report['at_risk']);
        $this->assertSame(1, $report['lost']);
        $this->assertSame(66.7, $report['repeat_rate_percent']);
        $this->assertSame('وفادار', $report['top_guests'][0]['guest']->name);
        $this->assertSame('در حال دور شدن', $report['at_risk_guests'][0]['guest']->name);
    }

    public function test_tier_classification_matches_the_guest_page(): void
    {
        $guest = Guest::factory()->create();
        $this->stay($guest, 10, 60_000_000);

        $this->assertSame(Guest::tierFor(1, 60_000_000), $guest->loyaltyTier());
        $this->assertSame(LoyaltyTier::Gold, $guest->loyaltyTier());
    }

    public function test_the_page_renders_and_can_create_follow_ups_once(): void
    {
        $admin = $this->admin();
        $guest = Guest::factory()->create(['name' => 'مشتری در خطر']);
        $this->stay($guest, 340, 5_000_000);
        $this->stay($guest, 300, 5_000_000);

        $page = Livewire::actingAs($admin)->test(CustomerValueReportPage::class)->assertSee('مشتری در خطر');

        $page->callAction('followUpAtRisk');
        $page->callAction('followUpAtRisk');

        $this->assertSame(1, Task::where('task_type', TaskType::CrmFollowUp)->where('source_id', $guest->id)->count());
    }
}
