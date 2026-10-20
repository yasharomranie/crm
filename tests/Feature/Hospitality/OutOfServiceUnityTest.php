<?php

namespace Tests\Feature\Hospitality;

use App\Enums\BookingStatus;
use App\Enums\RoomStatus;
use App\Exceptions\RoomNotAvailableException;
use App\Filament\Resources\RoomBlocks\Pages\ManageRoomBlocks;
use App\Models\Booking;
use App\Models\Room;
use App\Models\RoomBlock;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class OutOfServiceUnityTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $admin = User::factory()->create();
        $admin->assignRole(Role::firstOrCreate(['name' => 'admin']));

        return $admin;
    }

    public function test_setting_a_rooms_status_to_out_of_service_opens_a_block_in_the_list(): void
    {
        $room = Room::factory()->create();

        $room->update(['status' => RoomStatus::OutOfService]);

        $block = RoomBlock::sole();
        $this->assertSame($room->id, $block->room_id);
        $this->assertNull($block->ends_on);
        $this->assertSame(RoomBlock::SOURCE_STATUS, $block->source);

        Livewire::actingAs($this->admin())
            ->test(ManageRoomBlocks::class)
            ->assertCanSeeTableRecords([$block]);
    }

    public function test_creating_a_room_already_out_of_service_is_listed_too(): void
    {
        Room::factory()->create(['status' => RoomStatus::OutOfService]);

        $this->assertSame(1, RoomBlock::query()->whereNull('ends_on')->count());
    }

    public function test_the_status_is_not_duplicated_when_the_room_is_saved_again(): void
    {
        $room = Room::factory()->create(['status' => RoomStatus::OutOfService]);

        $room->update(['name' => 'نام تازه']);
        $room->update(['status' => RoomStatus::NeedsCleaning]);
        $room->update(['status' => RoomStatus::OutOfService]);

        $this->assertSame(1, RoomBlock::query()->where('room_id', $room->id)->whereNull('ends_on')->count());
    }

    public function test_an_out_of_service_room_cannot_be_booked(): void
    {
        $room = Room::factory()->create(['status' => RoomStatus::OutOfService]);

        $this->expectException(RoomNotAvailableException::class);

        Booking::factory()->create([
            'room_id' => $room->id,
            'status' => BookingStatus::Confirmed,
            'check_in_date' => today()->addDays(30)->toDateString(),
            'check_out_date' => today()->addDays(32)->toDateString(),
        ]);
    }

    public function test_an_out_of_service_room_is_not_offered_as_free(): void
    {
        $room = Room::factory()->create(['status' => RoomStatus::OutOfService]);

        $this->assertFalse(Room::query()->freeBetween(today()->addDays(10), today()->addDays(12))->whereKey($room->id)->exists());
    }

    public function test_putting_the_room_back_on_sale_closes_its_block(): void
    {
        $room = Room::factory()->create(['status' => RoomStatus::OutOfService]);

        $room->update(['status' => RoomStatus::Available]);

        $this->assertSame(0, RoomBlock::query()->count());
        $this->assertTrue(Room::query()->freeBetween(today()->addDay(), today()->addDays(3))->whereKey($room->id)->exists());
    }

    public function test_a_block_that_ran_for_days_ends_today_instead_of_vanishing(): void
    {
        $room = Room::factory()->create();
        $block = RoomBlock::factory()->create([
            'room_id' => $room->id,
            'starts_on' => today()->subDays(3)->toDateString(),
            'ends_on' => null,
            'source' => RoomBlock::SOURCE_STATUS,
        ]);
        $room->forceFill(['status' => RoomStatus::OutOfService])->saveQuietly();

        $room->update(['status' => RoomStatus::Available]);

        $this->assertTrue($block->fresh()->ends_on->isToday());
    }

    public function test_the_back_in_service_action_restores_the_rooms_status(): void
    {
        $room = Room::factory()->create(['status' => RoomStatus::OutOfService]);
        $block = RoomBlock::sole();

        Livewire::actingAs($this->admin())
            ->test(ManageRoomBlocks::class)
            ->callTableAction('backInService', $block)
            ->assertNotified('اتاق به سرویس برگشت.');

        $this->assertSame(RoomStatus::Available, $room->fresh()->status);
        $this->assertSame(0, RoomBlock::query()->count());
    }

    public function test_deleting_the_block_also_puts_the_room_back(): void
    {
        $room = Room::factory()->create(['status' => RoomStatus::OutOfService]);

        RoomBlock::sole()->delete();

        $this->assertSame(RoomStatus::Available, $room->fresh()->status);
    }

    public function test_a_dated_block_does_not_change_the_rooms_own_status(): void
    {
        $room = Room::factory()->create();
        RoomBlock::factory()->create(['room_id' => $room->id]);

        $this->assertSame(RoomStatus::Available, $room->fresh()->status);
    }

    public function test_staff_can_open_an_open_ended_block_without_an_end_date(): void
    {
        $room = Room::factory()->create();

        Livewire::actingAs($this->admin())
            ->test(ManageRoomBlocks::class)
            ->callAction('create', [
                'room_id' => $room->id,
                'starts_on' => today()->addDays(2)->toDateString(),
                'ends_on' => null,
                'reason' => 'تعمیر اساسی',
            ])
            ->assertHasNoActionErrors();

        $this->assertNull(RoomBlock::sole()->ends_on);
    }

    public function test_an_open_ended_block_over_a_future_booking_is_refused(): void
    {
        $booking = Booking::factory()->create([
            'status' => BookingStatus::Confirmed,
            'check_in_date' => today()->addDays(20)->toDateString(),
            'check_out_date' => today()->addDays(22)->toDateString(),
        ]);

        Livewire::actingAs($this->admin())
            ->test(ManageRoomBlocks::class)
            ->callAction('create', [
                'room_id' => $booking->room_id,
                'starts_on' => today()->addDays(2)->toDateString(),
                'ends_on' => null,
                'reason' => 'تعمیر اساسی',
            ])
            ->assertHasActionErrors(['starts_on']);
    }
}
