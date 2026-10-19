<?php

namespace Tests\Feature\Hospitality;

use App\Enums\BookingStatus;
use App\Enums\RoomStatus;
use App\Enums\SalesChannelType;
use App\Exceptions\ChannelAllotmentExceededException;
use App\Exceptions\RoomNotAvailableException;
use App\Filament\Resources\Bookings\Pages\ListBookings;
use App\Models\Booking;
use App\Models\ChannelAllotment;
use App\Models\Room;
use App\Models\RoomType;
use App\Models\SalesChannel;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use InvalidArgumentException;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ExtendAndChangeRoomTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $admin = User::factory()->create();
        $admin->assignRole(Role::firstOrCreate(['name' => 'admin']));

        return $admin;
    }

    private function roomOf(int $price, array $roomOverrides = []): Room
    {
        $type = RoomType::factory()->create(['base_price' => $price, 'min_nights' => 1]);

        return Room::factory()->create($roomOverrides + ['room_type_id' => $type->id]);
    }

    private function booking(Room $room, int $fromDay = 10, int $nights = 2, BookingStatus $status = BookingStatus::Confirmed, array $overrides = []): Booking
    {
        return Booking::factory()->create($overrides + [
            'room_id' => $room->id,
            'check_in_date' => now()->addDays($fromDay)->toDateString(),
            'check_out_date' => now()->addDays($fromDay + $nights)->toDateString(),
            'status' => $status,
            'adults_count' => 1,
            'children_count' => 0,
        ]);
    }

    public function test_extending_adds_the_extra_nights_to_the_folio(): void
    {
        $booking = $this->booking($this->roomOf(1_000_000));
        $this->assertSame(2_000_000, $booking->fresh()->totalAmount());

        $booking->extendStay(now()->addDays(15), $this->admin()->id);

        $booking = $booking->fresh(['charges']);
        $this->assertSame(now()->addDays(15)->toDateString(), $booking->check_out_date->toDateString());
        $this->assertSame(5_000_000, $booking->totalAmount());
        $this->assertSame(2, $booking->charges->count());
        $this->assertStringContainsString('تمدید اقامت 3 شب', $booking->charges->last()->title);
    }

    public function test_the_extension_uses_the_nightly_rates_and_extra_guests_of_the_new_nights(): void
    {
        $type = RoomType::factory()->create(['base_price' => 1_000_000, 'base_occupancy' => 1, 'extra_adult_fee' => 100_000, 'min_nights' => 1]);
        $booking = $this->booking(Room::factory()->create(['room_type_id' => $type->id]), 10, 2, BookingStatus::Confirmed, ['adults_count' => 2]);

        // 2 nights x (1M + 100k extra adult) = 2.2M so far; 2 more nights add the same per night.
        $this->assertSame(2_200_000, $booking->fresh()->totalAmount());
        $this->assertSame(2_200_000, $booking->extensionPrice(now()->addDays(14)));
    }

    public function test_extending_into_another_booking_is_refused_and_changes_nothing(): void
    {
        $room = $this->roomOf(1_000_000);
        $booking = $this->booking($room);
        $this->booking($room, 12, 2);

        try {
            $booking->extendStay(now()->addDays(13));
            $this->fail('The room is taken on those nights.');
        } catch (RoomNotAvailableException) {
            $this->addToAssertionCount(1);
        }

        $this->assertSame(now()->addDays(12)->toDateString(), $booking->fresh()->check_out_date->toDateString());
        $this->assertSame(now()->addDays(12)->toDateString(), $booking->check_out_date->toDateString());
        $this->assertSame(1, $booking->charges()->count());
    }

    public function test_extending_respects_the_channel_allotment(): void
    {
        $channel = SalesChannel::query()->create(['name' => 'اسنپ‌تریپ', 'type' => SalesChannelType::Ota, 'commission_rate' => 10, 'is_active' => true]);
        $first = $this->roomOf(1_000_000);
        $second = Room::factory()->create(['room_type_id' => $first->room_type_id]);
        ChannelAllotment::query()->create(['room_type_id' => $first->room_type_id, 'sales_channel_id' => $channel->id, 'allotment_count' => 1]);

        $booking = $this->booking($first, 10, 2, BookingStatus::Confirmed, ['sales_channel_id' => $channel->id]);
        $this->booking($second, 12, 2, BookingStatus::Confirmed);

        // The second booking is direct, so the channel still has its unit: extension works.
        $booking->extendStay(now()->addDays(13));
        $this->assertSame(now()->addDays(13)->toDateString(), $booking->fresh()->check_out_date->toDateString());

        // Another unit held by the same channel on a night the extension would cover is refused.
        $this->booking($second, 14, 2, BookingStatus::Confirmed, ['sales_channel_id' => $channel->id]);
        $this->expectException(ChannelAllotmentExceededException::class);
        $booking->fresh()->extendStay(now()->addDays(15));
    }

    public function test_only_a_live_stay_can_be_extended_and_only_to_a_later_date(): void
    {
        $room = $this->roomOf(1_000_000);
        $cancelled = $this->booking($room, 10, 2, BookingStatus::Cancelled);
        $live = $this->booking($room, 20, 2);

        foreach ([[$cancelled, now()->addDays(14)], [$live, now()->addDays(22)], [$live, now()->addDays(21)]] as [$booking, $date]) {
            try {
                $booking->extendStay($date);
                $this->fail('This should have been refused.');
            } catch (InvalidArgumentException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function test_changing_to_a_room_of_the_same_type_keeps_the_folio(): void
    {
        $old = $this->roomOf(1_000_000);
        $new = Room::factory()->create(['room_type_id' => $old->room_type_id]);
        $booking = $this->booking($old);

        $booking->changeRoom($new);

        $booking = $booking->fresh(['charges']);
        $this->assertSame($new->id, $booking->room_id);
        $this->assertSame(2_000_000, $booking->totalAmount());
        $this->assertSame(1, $booking->charges->count());
    }

    public function test_a_different_type_adds_the_price_difference_unless_it_is_a_free_upgrade(): void
    {
        $cheap = $this->roomOf(1_000_000);
        $suite = $this->roomOf(1_500_000);
        $booking = $this->booking($cheap);
        $this->assertSame(1_000_000, $booking->roomChangeDifference($suite));

        $booking->changeRoom($suite);
        $this->assertSame(3_000_000, $booking->fresh(['charges'])->totalAmount());
        $this->assertStringContainsString('مابه‌التفاوت', $booking->fresh(['charges'])->charges->last()->title);

        $other = $this->booking($cheap, 30);
        $other->changeRoom($suite, chargeDifference: false);
        $this->assertSame(2_000_000, $other->fresh(['charges'])->totalAmount());

        $downgrade = $this->booking($suite, 40);
        $downgrade->changeRoom($cheap);
        $this->assertSame(2_000_000, $downgrade->fresh(['charges'])->totalAmount());
    }

    public function test_a_room_that_is_taken_is_refused_and_the_booking_stays_put(): void
    {
        $old = $this->roomOf(1_000_000);
        $busy = Room::factory()->create(['room_type_id' => $old->room_type_id]);
        $booking = $this->booking($old);
        $this->booking($busy, 11, 3);

        try {
            $booking->changeRoom($busy);
            $this->fail('The target room is busy.');
        } catch (RoomNotAvailableException) {
            $this->addToAssertionCount(1);
        }

        $this->assertSame($old->id, $booking->fresh()->room_id);
        $this->assertSame($old->id, $booking->room_id);
    }

    public function test_moving_a_guest_in_the_house_swaps_the_room_statuses(): void
    {
        $old = $this->roomOf(1_000_000, ['status' => RoomStatus::Occupied]);
        $new = Room::factory()->create(['room_type_id' => $old->room_type_id, 'status' => RoomStatus::Available]);
        $booking = $this->booking($old, 0, 3, BookingStatus::CheckedIn);

        $booking->changeRoom($new);

        $this->assertSame(RoomStatus::NeedsCleaning, $old->fresh()->status);
        $this->assertSame(RoomStatus::Occupied, $new->fresh()->status);
    }

    public function test_free_between_lists_only_rooms_without_a_clash(): void
    {
        $free = $this->roomOf(1_000_000, ['name' => 'آزاد']);
        $busy = $this->roomOf(1_000_000, ['name' => 'مشغول']);
        $inactive = $this->roomOf(1_000_000, ['name' => 'غیرفعال', 'is_active' => false]);
        $this->booking($busy, 10, 3);
        $mine = $this->booking($free, 10, 3);

        // The booking itself makes its own room look taken ...
        $this->assertSame([], Room::query()->freeBetween(Carbon::parse($mine->check_in_date), Carbon::parse($mine->check_out_date))->pluck('name')->all());

        // ... unless it is the one being moved.
        $ignoringMine = Room::query()->freeBetween(Carbon::parse($mine->check_in_date), Carbon::parse($mine->check_out_date), $mine->id)->pluck('name')->all();
        $this->assertSame(['آزاد'], $ignoringMine);
        $this->assertNotContains($inactive->name, $ignoringMine);
    }

    public function test_the_table_actions_extend_and_move_a_booking(): void
    {
        $old = $this->roomOf(1_000_000);
        $new = Room::factory()->create(['room_type_id' => $old->room_type_id]);
        $booking = $this->booking($old);

        $page = Livewire::actingAs($this->admin())->test(ListBookings::class);

        $page->callTableAction('extendStay', $booking, data: ['new_check_out' => now()->addDays(14)->toDateString()])
            ->assertNotified('اقامت تمدید شد');
        $this->assertSame(now()->addDays(14)->toDateString(), $booking->fresh()->check_out_date->toDateString());

        $page->callTableAction('changeRoom', $booking, data: ['room_id' => $new->id, 'charge_difference' => true])
            ->assertNotified('اتاق عوض شد');
        $this->assertSame($new->id, $booking->fresh()->room_id);
    }
}
