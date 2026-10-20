<?php

namespace Tests\Feature\Hospitality;

use App\Domain\Hospitality\MorningDigest;
use App\Enums\BookingPaymentMethod;
use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\HandoverNote;
use App\Models\Room;
use App\Models\RoomBlock;
use App\Models\User;
use App\Notifications\HotelMorningDigestNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class MorningDigestTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $admin = User::factory()->create();
        $admin->assignRole(Role::firstOrCreate(['name' => 'admin']));

        return $admin;
    }

    private function stay(BookingStatus $status, int $fromDay, int $toDay): Booking
    {
        return Booking::factory()->create([
            'room_id' => Room::factory()->create()->id,
            'status' => $status,
            'check_in_date' => today()->addDays($fromDay)->toDateString(),
            'check_out_date' => today()->addDays($toDay)->toDateString(),
        ]);
    }

    public function test_the_summary_counts_the_days_work(): void
    {
        $this->stay(BookingStatus::Confirmed, 0, 2);
        $this->stay(BookingStatus::CheckedIn, -2, 0);
        $this->stay(BookingStatus::CheckedIn, -1, 3);
        $this->stay(BookingStatus::Confirmed, 1, 3);
        RoomBlock::factory()->create(['starts_on' => today()->subDay()->toDateString(), 'ends_on' => today()->addDay()->toDateString()]);
        HandoverNote::factory()->create();
        HandoverNote::factory()->create(['resolved_at' => now()]);

        $summary = app(MorningDigest::class)->summary(today());

        $this->assertSame(1, $summary['arrivals']);
        $this->assertSame(1, $summary['departures']);
        $this->assertSame(2, $summary['in_house']);
        $this->assertSame(1, $summary['rooms_out']);
        $this->assertSame(1, $summary['open_notes']);
    }

    public function test_yesterdays_money_is_summed_net_of_refunds(): void
    {
        $booking = $this->stay(BookingStatus::CheckedIn, -2, 1);
        $payment = $booking->recordPayment(300_000, BookingPaymentMethod::Cash);
        $payment->forceFill(['paid_at' => today()->subDay()->setTime(15, 0)])->save();

        $this->assertSame(300_000, app(MorningDigest::class)->summary(today())['yesterday_cash']);
    }

    public function test_admins_are_notified_on_a_working_day(): void
    {
        Notification::fake();
        $admin = $this->admin();
        $this->stay(BookingStatus::Confirmed, 0, 2);

        $this->assertSame(1, app(MorningDigest::class)->notifyStaff());

        Notification::assertSentTo($admin, HotelMorningDigestNotification::class);
    }

    public function test_a_quiet_day_sends_nothing(): void
    {
        Notification::fake();
        $this->admin();

        $this->assertSame(0, app(MorningDigest::class)->notifyStaff());

        Notification::assertNothingSent();
    }
}
