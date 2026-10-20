<?php

namespace Tests\Feature\Hospitality;

use App\Enums\BookingPaymentMethod;
use App\Enums\BookingStatus;
use App\Filament\Resources\BookingGroups\Pages\CreateBookingGroup;
use App\Filament\Resources\BookingGroups\Pages\ListBookingGroups;
use App\Filament\Resources\BookingGroups\Pages\ViewBookingGroup;
use App\Filament\Resources\BookingGroups\RelationManagers\BookingsRelationManager;
use App\Models\Booking;
use App\Models\BookingCharge;
use App\Models\BookingGroup;
use App\Models\Guest;
use App\Models\Room;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class BookingGroupTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $admin = User::factory()->create();
        $admin->assignRole(Role::firstOrCreate(['name' => 'admin']));

        return $admin;
    }

    private function group(): BookingGroup
    {
        return BookingGroup::create(['guest_id' => Guest::factory()->create()->id, 'name' => 'خانواده‌ی احمدی']);
    }

    /**
     * A room of the group with a folio of exactly $total, arriving $fromDay days from now.
     */
    private function roomBooking(BookingGroup $group, int $total, int $fromDay = 10, BookingStatus $status = BookingStatus::Confirmed): Booking
    {
        $booking = Booking::factory()->create([
            'booking_group_id' => $group->id,
            'guest_id' => $group->guest_id,
            'status' => $status,
            'check_in_date' => now()->addDays($fromDay)->toDateString(),
            'check_out_date' => now()->addDays($fromDay + 2)->toDateString(),
        ]);
        $booking->charges()->delete();
        BookingCharge::query()->create(['booking_id' => $booking->id, 'title' => 'اقامت', 'quantity' => 1, 'unit_amount' => $total]);

        return $booking->fresh();
    }

    public function test_a_group_gets_a_code_and_sums_its_rooms(): void
    {
        $group = $this->group();
        $this->roomBooking($group, 1_000_000);
        $this->roomBooking($group, 2_000_000, 12);

        $this->assertMatchesRegularExpression('/^GRP-\d{6}$/', $group->code);
        $this->assertSame(3_000_000, $group->totalAmount());
        $this->assertSame(0, $group->paidAmount());
        $this->assertSame(3_000_000, $group->balanceDue());
    }

    public function test_one_payment_is_shared_over_the_rooms_in_order_of_arrival(): void
    {
        $group = $this->group();
        $later = $this->roomBooking($group, 2_000_000, 20);
        $first = $this->roomBooking($group, 1_000_000, 10);

        $allocation = $group->receivePayment(1_500_000, BookingPaymentMethod::Cash, 'REF-1', null, $this->admin()->id);

        $this->assertSame([$first->id => 1_000_000, $later->id => 500_000], $allocation);
        $this->assertSame(0, $first->fresh(['charges', 'payments'])->balanceDue());
        $this->assertSame(1_500_000, $later->fresh(['charges', 'payments'])->balanceDue());
        $payment = $first->payments()->first();
        $this->assertSame(BookingPaymentMethod::Cash, $payment->method);
        $this->assertSame('REF-1', $payment->reference);
        $this->assertStringContainsString($group->code, $payment->note);
        $this->assertSame(1_500_000, $group->fresh()->balanceDue());
    }

    public function test_a_payment_beyond_the_group_balance_or_of_zero_is_refused_whole(): void
    {
        $group = $this->group();
        $booking = $this->roomBooking($group, 1_000_000);

        foreach ([0, 1_000_001] as $amount) {
            try {
                $group->receivePayment($amount, BookingPaymentMethod::Cash);
                $this->fail("{$amount} should have been refused.");
            } catch (InvalidArgumentException) {
                $this->addToAssertionCount(1);
            }
        }

        $this->assertSame(0, $booking->payments()->count());
    }

    public function test_a_cancelled_room_owes_only_its_fee(): void
    {
        $group = $this->group();
        $this->roomBooking($group, 1_000_000);
        $cancelled = $this->roomBooking($group, 2_000_000, 12);
        $cancelled->cancel('تغییر برنامه', false, true);

        $this->assertSame(1_000_000, $group->fresh()->totalAmount());
    }

    public function test_cancelling_the_group_leaves_rooms_already_in_the_house(): void
    {
        $group = $this->group();
        $pending = $this->roomBooking($group, 1_000_000, 10, BookingStatus::Pending);
        $confirmed = $this->roomBooking($group, 1_000_000, 12);
        $inHouse = $this->roomBooking($group, 1_000_000, 0, BookingStatus::CheckedIn);

        $this->assertSame(2, $group->cancelAll('لغو سفر', true));

        $this->assertSame(BookingStatus::Cancelled, $pending->fresh()->status);
        $this->assertSame(BookingStatus::Cancelled, $confirmed->fresh()->status);
        $this->assertSame('لغو سفر', $confirmed->fresh()->cancellation_reason);
        $this->assertSame(BookingStatus::CheckedIn, $inHouse->fresh()->status);
    }

    public function test_a_group_is_made_from_the_form_with_one_booking_per_room(): void
    {
        $guest = Guest::factory()->create();
        $one = Room::factory()->create();
        $two = Room::factory()->create();

        Livewire::actingAs($this->admin())
            ->test(CreateBookingGroup::class)
            ->fillForm([
                'guest_id' => $guest->id,
                'name' => 'تور خانوادگی',
                'check_in_date' => now()->addDays(10)->toDateString(),
                'check_out_date' => now()->addDays(12)->toDateString(),
                'status' => 'confirmed',
                'source' => 'phone',
                'rooms' => [
                    ['room_id' => $one->id, 'adults_count' => 2, 'children_count' => 1],
                    ['room_id' => $two->id, 'adults_count' => 3, 'children_count' => 0],
                ],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $group = BookingGroup::sole();
        $this->assertSame('تور خانوادگی', $group->name);
        $this->assertSame(2, $group->bookings()->count());
        $this->assertEqualsCanonicalizing([$one->id, $two->id], $group->bookings()->pluck('room_id')->all());
        $this->assertSame([BookingStatus::Confirmed], $group->bookings->pluck('status')->unique()->values()->all());
        $this->assertSame($guest->id, $group->bookings->first()->guest_id);
    }

    public function test_a_room_that_is_already_taken_cannot_be_chosen(): void
    {
        $taken = Room::factory()->create();
        Booking::factory()->create([
            'room_id' => $taken->id,
            'check_in_date' => now()->addDays(10)->toDateString(),
            'check_out_date' => now()->addDays(12)->toDateString(),
        ]);

        Livewire::actingAs($this->admin())
            ->test(CreateBookingGroup::class)
            ->fillForm([
                'guest_id' => Guest::factory()->create()->id,
                'check_in_date' => now()->addDays(10)->toDateString(),
                'check_out_date' => now()->addDays(12)->toDateString(),
                'status' => 'pending',
                'source' => 'phone',
                'rooms' => [['room_id' => $taken->id, 'adults_count' => 2, 'children_count' => 0]],
            ])
            ->call('create')
            ->assertHasFormErrors(['rooms.0.room_id']);

        $this->assertSame(0, BookingGroup::count());
    }

    public function test_the_list_pays_and_shows_the_group_and_its_rooms(): void
    {
        $group = $this->group();
        $booking = $this->roomBooking($group, 1_000_000);
        $admin = $this->admin();

        Livewire::actingAs($admin)
            ->test(ListBookingGroups::class)
            ->assertCanSeeTableRecords([$group])
            ->callTableAction('groupPayment', $group, data: ['amount' => 400_000, 'method' => 'card', 'reference' => 'X1'])
            ->assertNotified('پرداخت گروهی ثبت شد');
        $this->assertSame(600_000, $booking->fresh(['charges', 'payments'])->balanceDue());

        Livewire::actingAs($admin)
            ->test(ViewBookingGroup::class, ['record' => $group->getRouteKey()])
            ->assertSee($group->code)
            ->assertSee('خانواده‌ی احمدی');

        Livewire::actingAs($admin)
            ->test(BookingsRelationManager::class, ['ownerRecord' => $group, 'pageClass' => ViewBookingGroup::class])
            ->assertCanSeeTableRecords([$booking]);
    }

    public function test_the_group_invoice_lists_every_room_and_the_totals_for_staff_only(): void
    {
        $group = $this->group();
        $first = $this->roomBooking($group, 1_000_000);
        $this->roomBooking($group, 2_000_000, 12);
        $first->recordPayment(300_000, BookingPaymentMethod::Cash, reference: 'REF-G');

        $this->actingAs($this->admin())
            ->get(route('booking-groups.invoice', $group))
            ->assertOk()
            ->assertSee($group->code)
            ->assertSee('REF-G')
            ->assertSee('جمع کل گروه')
            ->assertSee('مانده‌ی قابل پرداخت گروه');

        $user = User::factory()->create();
        $user->assignRole(Role::create(['name' => 'بدون دسترسی گروه']));
        $this->actingAs($user)->get(route('booking-groups.invoice', $group))->assertForbidden();
    }
}
