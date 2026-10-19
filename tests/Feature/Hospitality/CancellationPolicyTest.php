<?php

namespace Tests\Feature\Hospitality;

use App\Enums\BookingPaymentKind;
use App\Enums\BookingPaymentMethod;
use App\Enums\BookingStatus;
use App\Filament\Resources\Bookings\Pages\ListBookings;
use App\Models\Booking;
use App\Models\BookingCharge;
use App\Models\CancellationPolicy;
use App\Models\ExternalApiClient;
use App\Models\Guest;
use App\Models\Room;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class CancellationPolicyTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $admin = User::factory()->create();
        $admin->assignRole(Role::firstOrCreate(['name' => 'admin']));

        return $admin;
    }

    private function defaultPolicy(): CancellationPolicy
    {
        return CancellationPolicy::factory()->steps()->create(['name' => 'استاندارد', 'is_default' => true, 'late_penalty_percent' => 100, 'no_show_penalty_percent' => 80]);
    }

    /**
     * A booking whose folio is exactly $total, checking in $daysAhead days from now.
     */
    private function booking(int $daysAhead = 10, int $total = 1_000_000, ?Guest $guest = null, BookingStatus $status = BookingStatus::Confirmed): Booking
    {
        $booking = Booking::factory()->create([
            'status' => $status,
            'guest_id' => ($guest ?? Guest::factory()->create())->id,
            'check_in_date' => now()->addDays($daysAhead)->toDateString(),
            'check_out_date' => now()->addDays($daysAhead + 2)->toDateString(),
        ]);
        $booking->charges()->delete();
        BookingCharge::query()->create(['booking_id' => $booking->id, 'title' => 'اقامت', 'quantity' => 1, 'unit_amount' => $total]);

        return $booking->fresh();
    }

    public function test_a_booking_keeps_the_terms_of_the_default_policy_it_was_made_under(): void
    {
        $policy = $this->defaultPolicy();
        $booking = $this->booking();

        $this->assertSame($policy->id, $booking->cancellation_policy_id);
        $this->assertSame('استاندارد', $booking->cancellation_terms['name']);
        $this->assertSame([['days' => 7, 'percent' => 0], ['days' => 3, 'percent' => 30], ['days' => 1, 'percent' => 70]], $booking->cancellation_terms['rules']);

        $policy->rules()->delete();
        $policy->update(['late_penalty_percent' => 0]);

        $this->assertCount(3, $booking->fresh()->cancellation_terms['rules']);
        $this->assertSame(100, $booking->fresh()->cancellation_terms['late_percent']);
    }

    public function test_a_booking_can_choose_another_policy_and_without_any_policy_nothing_is_charged(): void
    {
        $this->defaultPolicy();
        $strict = CancellationPolicy::factory()->create(['name' => 'غیرقابل بازگشت', 'late_penalty_percent' => 100]);

        $booking = Booking::factory()->create(['cancellation_policy_id' => $strict->id]);
        $this->assertSame('غیرقابل بازگشت', $booking->cancellation_terms['name']);

        CancellationPolicy::query()->delete();
        $free = Booking::factory()->create();
        $this->assertNull($free->cancellation_terms);
        $this->assertSame(0, $free->cancellationFeeFor());
        $this->assertSame(0, $free->cancellationFeeFor(noShow: true));
    }

    public function test_only_one_policy_is_the_default(): void
    {
        $first = CancellationPolicy::factory()->create(['is_default' => true]);
        $second = CancellationPolicy::factory()->create(['is_default' => true]);

        $this->assertFalse($first->fresh()->is_default);
        $this->assertTrue($second->fresh()->is_default);
        $this->assertSame($second->id, CancellationPolicy::defaultPolicy()->id);
    }

    public function test_the_fee_follows_the_step_for_how_many_days_are_left(): void
    {
        $this->defaultPolicy();

        $this->assertSame(0, $this->booking(10)->cancellationFeeFor());
        $this->assertSame(0, $this->booking(7)->cancellationFeeFor());
        $this->assertSame(300_000, $this->booking(5)->cancellationFeeFor());
        $this->assertSame(700_000, $this->booking(2)->cancellationFeeFor());
        $this->assertSame(1_000_000, $this->booking(0)->cancellationFeeFor());
        $this->assertSame(800_000, $this->booking(10)->cancellationFeeFor(noShow: true));
    }

    public function test_cancelling_charges_the_fee_and_a_waiver_removes_it(): void
    {
        $this->defaultPolicy();
        $booking = $this->booking(2);

        $booking->cancel('مهمان منصرف شد');

        $booking = $booking->fresh();
        $this->assertSame(BookingStatus::Cancelled, $booking->status);
        $this->assertSame(700_000, $booking->cancellation_fee);
        $this->assertSame('مهمان منصرف شد', $booking->cancellation_reason);
        $this->assertSame(Booking::CANCELLED_BY_GUEST, $booking->cancellation_kind);
        $this->assertNotNull($booking->cancelled_at);
        $this->assertSame(700_000, $booking->balanceDue());

        $waived = $this->booking(2);
        $waived->cancel(waiveFee: true);
        $this->assertSame(0, $waived->fresh()->cancellation_fee);
        $this->assertSame(0, $waived->fresh()->balanceDue());
    }

    public function test_a_no_show_uses_its_own_percentage(): void
    {
        $this->defaultPolicy();
        $booking = $this->booking(0);

        $booking->cancel(noShow: true);

        $booking = $booking->fresh();
        $this->assertSame(800_000, $booking->cancellation_fee);
        $this->assertSame(Booking::CANCELLED_NO_SHOW, $booking->cancellation_kind);
        $this->assertSame('عدم حضور', $booking->cancellationKindLabel());
    }

    public function test_a_booking_that_has_started_cannot_be_cancelled_this_way(): void
    {
        $booking = $this->booking(0, status: BookingStatus::CheckedIn);

        $this->expectException(InvalidArgumentException::class);

        $booking->cancel();
    }

    public function test_the_fee_is_taken_from_received_money_first_then_from_the_wallet_and_the_rest_stays_due(): void
    {
        $this->defaultPolicy();
        $guest = Guest::factory()->create(['wallet_balance' => 400_000]);
        $booking = $this->booking(2, guest: $guest);
        $booking->payFromWallet(400_000);
        $booking->fresh()->recordPayment(200_000, BookingPaymentMethod::Cash);

        $booking->fresh()->cancel();

        $booking = $booking->fresh();
        $this->assertSame(700_000, $booking->cancellation_fee);
        // 200k of cash covers part of the 700k fee; the wallet's 400k is needed too, so nothing goes back
        $this->assertSame(400_000, (int) $booking->wallet_paid_amount);
        $this->assertSame(0, (int) $guest->fresh()->wallet_balance);
        $this->assertSame(100_000, $booking->balanceDue());
        $this->assertSame(0, $booking->refundableAmount());
    }

    public function test_a_free_cancellation_returns_the_wallet_money_and_leaves_the_cash_to_refund(): void
    {
        $this->defaultPolicy();
        $guest = Guest::factory()->create(['wallet_balance' => 300_000]);
        $booking = $this->booking(10, guest: $guest);
        $booking->payFromWallet(300_000);
        $booking->fresh()->recordPayment(200_000, BookingPaymentMethod::Card);

        $booking->fresh()->cancel();

        $booking = $booking->fresh();
        $this->assertSame(300_000, (int) $guest->fresh()->wallet_balance);
        $this->assertSame(0, (int) $booking->wallet_paid_amount);
        $this->assertSame(200_000, $booking->refundableAmount());

        $booking->recordPayment(200_000, BookingPaymentMethod::Card, BookingPaymentKind::Refund);

        $this->assertSame(0, $booking->fresh()->refundableAmount());
    }

    public function test_an_unpaid_fee_can_be_collected_but_nothing_beyond_it(): void
    {
        $this->defaultPolicy();
        $booking = $this->booking(2);
        $booking->cancel();
        $booking = $booking->fresh();

        $booking->recordPayment(700_000, BookingPaymentMethod::Cash);

        $this->assertSame(0, $booking->fresh()->balanceDue());

        $this->expectException(InvalidArgumentException::class);
        $booking->fresh()->recordPayment(1, BookingPaymentMethod::Cash);
    }

    public function test_editing_the_status_to_cancelled_applies_the_policy_too(): void
    {
        $this->defaultPolicy();
        $guest = Guest::factory()->create(['wallet_balance' => 100_000]);
        $booking = $this->booking(5, guest: $guest);
        $booking->payFromWallet(100_000);

        $booking->fresh()->update(['status' => BookingStatus::Cancelled]);

        $booking = $booking->fresh();
        $this->assertSame(300_000, $booking->cancellation_fee);
        $this->assertNotNull($booking->cancelled_at);
        $this->assertSame(100_000, (int) $booking->wallet_paid_amount);
    }

    public function test_reactivating_a_cancelled_booking_clears_the_cancellation(): void
    {
        $this->defaultPolicy();
        $booking = $this->booking(2);
        $booking->cancel('اشتباه');

        $booking->fresh()->update(['status' => BookingStatus::Confirmed]);

        $booking = $booking->fresh();
        $this->assertNull($booking->cancelled_at);
        $this->assertNull($booking->cancellation_kind);
        $this->assertNull($booking->cancellation_reason);
        $this->assertSame(0, $booking->cancellation_fee);
    }

    public function test_the_table_actions_cancel_and_mark_no_show(): void
    {
        $this->defaultPolicy();
        $booking = $this->booking(2);
        $arriving = $this->booking(0);

        Livewire::actingAs($this->admin())
            ->test(ListBookings::class)
            ->callTableAction('cancelBooking', $booking, data: ['reason' => 'تغییر برنامه', 'waive_fee' => false])
            ->assertHasNoTableActionErrors()
            ->callTableAction('markNoShow', $arriving, data: ['waive_fee' => true])
            ->assertHasNoTableActionErrors();

        $this->assertSame(BookingStatus::Cancelled, $booking->fresh()->status);
        $this->assertSame(700_000, $booking->fresh()->cancellation_fee);
        $this->assertSame(Booking::CANCELLED_NO_SHOW, $arriving->fresh()->cancellation_kind);
        $this->assertSame(0, $arriving->fresh()->cancellation_fee);
    }

    public function test_the_cancel_action_is_hidden_once_a_stay_has_started(): void
    {
        $inHouse = $this->booking(0, status: BookingStatus::CheckedIn);

        Livewire::actingAs($this->admin())
            ->test(ListBookings::class)
            ->assertTableActionHidden('cancelBooking', $inHouse)
            ->assertTableActionHidden('markNoShow', $inHouse);
    }

    public function test_the_external_api_shows_the_terms_and_the_fee(): void
    {
        $this->defaultPolicy();
        $room = Room::factory()->create();
        $client = ExternalApiClient::factory()->create();
        $headers = ['X-API-Key' => $client->api_key, 'X-API-Secret' => 'test-secret'];

        $create = $this->postJson('/api/v1/external/bookings', [
            'room_id' => $room->id,
            'check_in_date' => now()->addDays(2)->toDateString(),
            'check_out_date' => now()->addDays(4)->toDateString(),
            'guest' => ['name' => 'سارا', 'mobile' => '09124445566'],
        ], [...$headers, 'Idempotency-Key' => 'cx-1'])->assertStatus(201);

        $booking = Booking::where('reference_code', $create->json('reference_code'))->sole();

        $this->getJson("/api/v1/external/bookings/{$booking->reference_code}", $headers)
            ->assertOk()
            ->assertJsonPath('cancellation_terms.name', 'استاندارد')
            ->assertJsonPath('cancellation_fee', null);

        $booking->cancel();

        $this->getJson("/api/v1/external/bookings/{$booking->reference_code}", $headers)
            ->assertOk()
            ->assertJsonPath('cancellation_fee', (int) round($booking->totalAmount() * 0.7));
    }
}
