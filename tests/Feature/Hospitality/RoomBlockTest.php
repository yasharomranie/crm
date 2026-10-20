<?php

namespace Tests\Feature\Hospitality;

use App\Enums\BookingStatus;
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

class RoomBlockTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $admin = User::factory()->create();
        $admin->assignRole(Role::firstOrCreate(['name' => 'admin']));

        return $admin;
    }

    public function test_a_blocked_room_is_not_free_for_overlapping_nights_only(): void
    {
        $room = Room::factory()->create();
        RoomBlock::factory()->create(['room_id' => $room->id, 'starts_on' => now()->addDays(5)->toDateString(), 'ends_on' => now()->addDays(8)->toDateString()]);

        $this->assertFalse(Room::query()->freeBetween(now()->addDays(6), now()->addDays(7))->whereKey($room->id)->exists());
        $this->assertFalse(Room::query()->freeBetween(now()->addDays(3), now()->addDays(6))->whereKey($room->id)->exists());
        $this->assertTrue(Room::query()->freeBetween(now()->addDays(8), now()->addDays(10))->whereKey($room->id)->exists());
        $this->assertTrue(Room::query()->freeBetween(now()->addDays(2), now()->addDays(5))->whereKey($room->id)->exists());
    }

    public function test_a_booking_cannot_be_made_on_a_blocked_room(): void
    {
        $room = Room::factory()->create();
        RoomBlock::factory()->create(['room_id' => $room->id]);

        $this->expectException(RoomNotAvailableException::class);

        Booking::factory()->create([
            'room_id' => $room->id,
            'status' => BookingStatus::Confirmed,
            'check_in_date' => now()->addDays(6)->toDateString(),
            'check_out_date' => now()->addDays(7)->toDateString(),
        ]);
    }

    public function test_staff_block_a_room_from_the_page(): void
    {
        $room = Room::factory()->create();

        Livewire::actingAs($this->admin())
            ->test(ManageRoomBlocks::class)
            ->callAction('create', [
                'room_id' => $room->id,
                'starts_on' => now()->addDays(2)->toDateString(),
                'ends_on' => now()->addDays(4)->toDateString(),
                'reason' => 'تعمیر کولر',
            ])
            ->assertHasNoActionErrors();

        $block = RoomBlock::sole();
        $this->assertSame($room->id, $block->room_id);
        $this->assertNotNull($block->created_by);
    }

    public function test_blocking_over_an_active_booking_is_refused(): void
    {
        $booking = Booking::factory()->create([
            'status' => BookingStatus::Confirmed,
            'check_in_date' => now()->addDays(2)->toDateString(),
            'check_out_date' => now()->addDays(4)->toDateString(),
        ]);

        Livewire::actingAs($this->admin())
            ->test(ManageRoomBlocks::class)
            ->callAction('create', [
                'room_id' => $booking->room_id,
                'starts_on' => now()->addDays(3)->toDateString(),
                'ends_on' => now()->addDays(6)->toDateString(),
                'reason' => 'رنگ‌آمیزی',
            ])
            ->assertHasActionErrors(['starts_on']);

        $this->assertSame(0, RoomBlock::query()->count());
    }

    public function test_the_page_is_forbidden_without_permission(): void
    {
        $user = User::factory()->create();
        $user->assignRole(Role::create(['name' => 'بدون دسترسی بستن اتاق']));

        Livewire::actingAs($user)->test(ManageRoomBlocks::class)->assertForbidden();
    }
}
