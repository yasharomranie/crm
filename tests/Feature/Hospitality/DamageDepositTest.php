<?php

namespace Tests\Feature\Hospitality;

use App\Enums\BookingPaymentMethod;
use App\Enums\BookingStatus;
use App\Filament\Pages\TodayArrivals;
use App\Filament\Resources\Bookings\Pages\ListBookings;
use App\Models\Booking;
use App\Models\DamageDeposit;
use App\Models\ExternalApiClient;
use App\Models\Room;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class DamageDepositTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $admin = User::factory()->create();
        $admin->assignRole(Role::firstOrCreate(['name' => 'admin']));

        return $admin;
    }

    private function booking(BookingStatus $status = BookingStatus::Confirmed, int $fromDay = 10): Booking
    {
        return Booking::factory()->create([
            'status' => $status,
            'check_in_date' => now()->addDays($fromDay)->toDateString(),
            'check_out_date' => now()->addDays($fromDay + 2)->toDateString(),
        ]);
    }

    public function test_receiving_a_deposit_does_not_touch_the_folio(): void
    {
        $booking = $this->booking();
        $balanceBefore = $booking->fresh(['charges', 'payments'])->balanceDue();

        $deposit = $booking->receiveDamageDeposit(3_000_000, BookingPaymentMethod::Cash, $this->admin()->id);

        $this->assertSame(3_000_000, $deposit->held_amount);
        $this->assertSame(BookingPaymentMethod::Cash, $deposit->method);
        $booking = $booking->fresh(['charges', 'payments']);
        $this->assertSame($balanceBefore, $booking->balanceDue());
        $this->assertSame(0, $booking->receivedAmount());
        $this->assertSame(3_000_000, $deposit->outstandingAmount());
    }

    public function test_more_money_can_be_added_until_the_deposit_is_settled(): void
    {
        $booking = $this->booking();
        $booking->receiveDamageDeposit(1_000_000, BookingPaymentMethod::Cash);
        $booking->receiveDamageDeposit(500_000, BookingPaymentMethod::Card);

        $this->assertSame(1_500_000, $booking->fresh()->damageDeposit->held_amount);
        $this->assertSame(1, DamageDeposit::count());

        $booking->settleDamageDeposit();

        $this->expectException(InvalidArgumentException::class);
        $booking->fresh()->receiveDamageDeposit(100_000, BookingPaymentMethod::Cash);
    }

    public function test_settling_keeps_the_damage_and_returns_the_rest_once(): void
    {
        $booking = $this->booking();
        $booking->receiveDamageDeposit(3_000_000, BookingPaymentMethod::Cash);

        $deposit = $booking->settleDamageDeposit(800_000, 'شکستن شیشه', $this->admin()->id);

        $this->assertSame(800_000, $deposit->deducted_amount);
        $this->assertSame(2_200_000, $deposit->refunded_amount);
        $this->assertSame('شکستن شیشه', $deposit->settlement_note);
        $this->assertTrue($deposit->isSettled());
        $this->assertSame(0, $deposit->outstandingAmount());

        $this->expectException(InvalidArgumentException::class);
        $booking->fresh()->settleDamageDeposit();
    }

    public function test_a_deduction_cannot_exceed_the_deposit_and_nothing_can_be_settled_without_one(): void
    {
        $booking = $this->booking();

        try {
            $booking->settleDamageDeposit();
            $this->fail('No deposit was taken.');
        } catch (InvalidArgumentException) {
            $this->addToAssertionCount(1);
        }

        $booking->receiveDamageDeposit(1_000_000, BookingPaymentMethod::Cash);

        foreach ([-1, 1_000_001] as $deduct) {
            try {
                $booking->settleDamageDeposit($deduct);
                $this->fail("{$deduct} should have been refused.");
            } catch (InvalidArgumentException) {
                $this->addToAssertionCount(1);
            }
        }

        $this->assertFalse($booking->fresh()->damageDeposit->isSettled());
    }

    public function test_a_deposit_cannot_be_taken_for_a_cancelled_or_finished_stay(): void
    {
        foreach ([BookingStatus::Cancelled, BookingStatus::CheckedOut] as $status) {
            try {
                $this->booking($status)->receiveDamageDeposit(100_000, BookingPaymentMethod::Cash);
                $this->fail("{$status->value} should have been refused.");
            } catch (InvalidArgumentException) {
                $this->addToAssertionCount(1);
            }
        }

        $this->expectException(InvalidArgumentException::class);
        $this->booking()->receiveDamageDeposit(0, BookingPaymentMethod::Cash);
    }

    public function test_cancelling_a_booking_gives_the_whole_deposit_back(): void
    {
        $booking = $this->booking();
        $booking->receiveDamageDeposit(2_000_000, BookingPaymentMethod::Cash);

        $booking->fresh()->cancel('تغییر برنامه', false, true);

        $deposit = $booking->fresh()->damageDeposit;
        $this->assertTrue($deposit->isSettled());
        $this->assertSame(0, $deposit->deducted_amount);
        $this->assertSame(2_000_000, $deposit->refunded_amount);
    }

    public function test_the_bookings_list_actions_receive_and_settle(): void
    {
        $booking = $this->booking();

        $page = Livewire::actingAs($this->admin())->test(ListBookings::class);

        $page->assertTableActionHidden('settleDamageDeposit', $booking)
            ->callTableAction('receiveDamageDeposit', $booking, data: ['amount' => 2_000_000, 'method' => 'cash'])
            ->assertNotified('ودیعه ثبت شد');
        $this->assertSame(2_000_000, $booking->fresh()->damageDeposit->held_amount);

        $page->callTableAction('settleDamageDeposit', $booking, data: ['deduct' => 500_000, 'note' => 'لکه'])
            ->assertNotified('ودیعه تسویه شد');
        $this->assertSame(1_500_000, $booking->fresh()->damageDeposit->refunded_amount);

        $page->assertTableActionHidden('settleDamageDeposit', $booking)
            ->assertTableActionHidden('receiveDamageDeposit', $booking);
    }

    public function test_the_invoice_and_the_api_show_the_deposit_apart_from_the_folio(): void
    {
        $booking = $this->booking();
        $booking->receiveDamageDeposit(2_000_000, BookingPaymentMethod::Cash);
        $booking->settleDamageDeposit(300_000, 'خسارت کوچک');

        $this->actingAs($this->admin())
            ->get(route('bookings.invoice', $booking))
            ->assertOk()
            ->assertSee('ودیعه‌ی خسارت')
            ->assertSee('خسارت کوچک');

        $client = ExternalApiClient::factory()->create();
        $booking->update(['external_api_client_id' => $client->id]);

        $this->getJson("/api/v1/external/bookings/{$booking->reference_code}", ['X-API-Key' => $client->api_key, 'X-API-Secret' => 'test-secret'])
            ->assertOk()
            ->assertJsonPath('damage_deposit.held', 2_000_000)
            ->assertJsonPath('damage_deposit.deducted', 300_000)
            ->assertJsonPath('damage_deposit.refunded', 1_700_000)
            ->assertJsonPath('damage_deposit.settled', true);
    }

    public function test_the_front_desk_list_flags_an_unsettled_deposit_on_departures(): void
    {
        $booking = Booking::factory()->create([
            'room_id' => Room::factory()->create()->id,
            'status' => BookingStatus::CheckedIn,
            'check_in_date' => now()->subDays(2)->toDateString(),
            'check_out_date' => now()->toDateString(),
        ]);
        $booking->receiveDamageDeposit(1_234_000, BookingPaymentMethod::Cash);

        Livewire::actingAs($this->admin())
            ->test(TodayArrivals::class)
            ->assertSee('ودیعه:');
    }
}
