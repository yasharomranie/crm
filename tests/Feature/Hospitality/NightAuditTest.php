<?php

namespace Tests\Feature\Hospitality;

use App\Domain\Hospitality\NightAudit;
use App\Enums\BookingPaymentKind;
use App\Enums\BookingPaymentMethod;
use App\Enums\BookingStatus;
use App\Enums\RoomStatus;
use App\Filament\Pages\NightAuditPage;
use App\Models\Booking;
use App\Models\BookingCharge;
use App\Models\DayClosing;
use App\Models\Room;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class NightAuditTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $admin = User::factory()->create();
        $admin->assignRole(Role::firstOrCreate(['name' => 'admin']));

        return $admin;
    }

    private function booking(BookingStatus $status, int $fromDay, int $nights = 2, int $total = 1_000_000, ?Room $room = null): Booking
    {
        $booking = Booking::factory()->create([
            'room_id' => ($room ?? Room::factory()->create())->id,
            'status' => $status,
            'check_in_date' => now()->addDays($fromDay)->toDateString(),
            'check_out_date' => now()->addDays($fromDay + $nights)->toDateString(),
        ]);
        $booking->charges()->delete();
        BookingCharge::query()->create(['booking_id' => $booking->id, 'title' => 'اقامت', 'quantity' => 1, 'unit_amount' => $total]);

        return $booking->fresh();
    }

    public function test_guests_who_never_came_and_guests_who_stayed_too_long_are_listed(): void
    {
        $missed = $this->booking(BookingStatus::Confirmed, -1);
        $arrivingToday = $this->booking(BookingStatus::Confirmed, 0);
        $future = $this->booking(BookingStatus::Confirmed, 3);
        $overdue = $this->booking(BookingStatus::CheckedIn, -4, 2);
        $leavingToday = $this->booking(BookingStatus::CheckedIn, -2, 2);

        $issues = app(NightAudit::class)->build(today())['issues'];

        $this->assertEqualsCanonicalizing([$missed->id, $arrivingToday->id], $issues['never_arrived']->pluck('id')->all());
        $this->assertSame([$overdue->id], $issues['overdue_departures']->pluck('id')->all());
        $this->assertSame([$leavingToday->id], $issues['departing_today']->pluck('id')->all());
        $this->assertNotContains($future->id, $issues['never_arrived']->pluck('id')->all());
    }

    public function test_unpaid_balances_of_in_house_and_recent_stays_are_listed(): void
    {
        $owing = $this->booking(BookingStatus::CheckedIn, -1, 3);
        $settled = $this->booking(BookingStatus::CheckedIn, -1, 3);
        $settled->recordPayment(1_000_000, BookingPaymentMethod::Cash);
        $recentOwing = $this->booking(BookingStatus::CheckedOut, -6, 2);
        $oldOwing = $this->booking(BookingStatus::CheckedOut, -60, 2);

        $unpaid = app(NightAudit::class)->build(today())['issues']['unpaid']->pluck('id')->all();

        $this->assertEqualsCanonicalizing([$owing->id, $recentOwing->id], $unpaid);
        $this->assertNotContains($settled->id, $unpaid);
        $this->assertNotContains($oldOwing->id, $unpaid);
    }

    public function test_deposits_still_held_after_the_stay_are_listed(): void
    {
        $left = $this->booking(BookingStatus::CheckedOut, -5, 2);
        $left->forceFill(['status' => BookingStatus::CheckedIn])->saveQuietly();
        $left->receiveDamageDeposit(1_000_000, BookingPaymentMethod::Cash);
        $left->forceFill(['status' => BookingStatus::CheckedOut])->saveQuietly();

        $stayingOn = $this->booking(BookingStatus::CheckedIn, -1, 4);
        $stayingOn->receiveDamageDeposit(500_000, BookingPaymentMethod::Cash);

        $settled = $this->booking(BookingStatus::CheckedIn, -5, 2);
        $settled->receiveDamageDeposit(500_000, BookingPaymentMethod::Cash);
        $settled->settleDamageDeposit();

        $ids = app(NightAudit::class)->build(today())['issues']['unsettled_deposits']->pluck('id')->all();

        $this->assertSame([$left->id], $ids);
    }

    public function test_rooms_whose_status_disagrees_with_the_bookings_are_flagged(): void
    {
        $ghost = Room::factory()->create(['status' => RoomStatus::Occupied]);
        $mismarked = Room::factory()->create(['status' => RoomStatus::Available]);
        $this->booking(BookingStatus::CheckedIn, -1, 3, 1_000_000, $mismarked);
        $fine = Room::factory()->create(['status' => RoomStatus::Occupied]);
        $this->booking(BookingStatus::CheckedIn, -1, 3, 1_000_000, $fine);

        $rows = app(NightAudit::class)->build(today())['issues']['room_mismatches'];

        $this->assertEqualsCanonicalizing([$ghost->id, $mismarked->id], $rows->pluck('room.id')->all());
    }

    public function test_the_days_money_is_summed_by_method_with_refunds_taken_off(): void
    {
        $booking = $this->booking(BookingStatus::CheckedIn, -1, 3, 3_000_000);
        $booking->recordPayment(1_000_000, BookingPaymentMethod::Cash);
        $booking->recordPayment(500_000, BookingPaymentMethod::Card, BookingPaymentKind::Deposit);
        $booking->recordPayment(200_000, BookingPaymentMethod::Cash, BookingPaymentKind::Refund);
        $yesterday = $booking->recordPayment(300_000, BookingPaymentMethod::Cash);
        $yesterday->forceFill(['paid_at' => now()->subDay()])->save();

        $cash = app(NightAudit::class)->build(today())['cash'];

        $this->assertSame(['cash' => 800_000, 'card' => 500_000], $cash['by_method']);
        $this->assertSame(1_300_000, $cash['total']);
        $this->assertSame(300_000, app(NightAudit::class)->build(today()->subDay())['cash']['total']);
    }

    public function test_closing_a_day_keeps_a_snapshot_once_and_never_for_the_future(): void
    {
        $this->booking(BookingStatus::Confirmed, -1);
        $admin = $this->admin();

        $closing = app(NightAudit::class)->close(today(), $admin->id);

        $this->assertSame($admin->id, $closing->closed_by);
        $this->assertSame(1, $closing->snapshot['issue_counts']['never_arrived']);
        $this->assertArrayHasKey('cash', $closing->snapshot);
        $this->assertSame($closing->id, app(NightAudit::class)->build(today())['closing']->id);

        foreach ([today(), today()->addDay()] as $date) {
            try {
                app(NightAudit::class)->close($date);
                $this->fail('This day cannot be closed.');
            } catch (InvalidArgumentException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function test_the_page_shows_the_report_marks_a_no_show_closes_the_day_and_steps_back(): void
    {
        $missed = $this->booking(BookingStatus::Confirmed, -1);

        $page = Livewire::actingAs($this->admin())
            ->test(NightAuditPage::class)
            ->assertSee($missed->guest->name)
            ->call('markNoShow', $missed->id);

        $this->assertSame(BookingStatus::Cancelled, $missed->fresh()->status);
        $this->assertSame(Booking::CANCELLED_NO_SHOW, $missed->fresh()->cancellation_kind);

        $page->call('closeDay');
        $this->assertSame(1, DayClosing::count());

        $page->call('shiftDay', -1)->assertSet('date', today()->subDay()->toDateString());
        $page->call('shiftDay', 1)->call('shiftDay', 1)->assertSet('date', today()->toDateString());
    }

    public function test_the_page_is_forbidden_without_permission(): void
    {
        $user = User::factory()->create();
        $user->assignRole(Role::create(['name' => 'بدون دسترسی بستن روز']));

        Livewire::actingAs($user)->test(NightAuditPage::class)->assertForbidden();
    }
}
