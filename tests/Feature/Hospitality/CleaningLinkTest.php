<?php

namespace Tests\Feature\Hospitality;

use App\Enums\BookingStatus;
use App\Enums\RoomStatus;
use App\Enums\TaskStatus;
use App\Enums\TaskType;
use App\Filament\Pages\RoomRack;
use App\Models\Booking;
use App\Models\Room;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class CleaningLinkTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $admin = User::factory()->create();
        $admin->assignRole(Role::firstOrCreate(['name' => 'admin']));

        return $admin;
    }

    private function stay(Room $room, BookingStatus $status = BookingStatus::CheckedIn, int $fromDay = -1, int $toDay = 2): Booking
    {
        return Booking::factory()->create([
            'room_id' => $room->id,
            'status' => $status,
            'check_in_date' => today()->addDays($fromDay)->toDateString(),
            'check_out_date' => today()->addDays($toDay)->toDateString(),
        ]);
    }

    public function test_finishing_the_checkout_cleaning_task_puts_the_room_back_on_sale(): void
    {
        $room = Room::factory()->create(['status' => RoomStatus::Occupied]);
        $task = $this->stay($room)->checkOut();

        $this->assertSame(RoomStatus::NeedsCleaning, $room->fresh()->status);

        $task->update(['status' => TaskStatus::Done]);

        $this->assertSame(RoomStatus::Available, $room->fresh()->status);
    }

    public function test_a_late_cleaning_task_never_frees_a_room_that_has_a_guest_again(): void
    {
        $room = Room::factory()->create(['status' => RoomStatus::Occupied]);
        $task = $this->stay($room, BookingStatus::CheckedIn, -3, 0)->checkOut();

        $this->stay($room, BookingStatus::Confirmed, 1, 3)->checkIn();
        $this->assertSame(RoomStatus::Occupied, $room->fresh()->status);

        $task->update(['status' => TaskStatus::Done]);

        $this->assertSame(RoomStatus::Occupied, $room->fresh()->status);
    }

    public function test_moving_a_guest_asks_housekeeping_to_clean_the_room_they_left(): void
    {
        $old = Room::factory()->create(['status' => RoomStatus::Occupied]);
        $new = Room::factory()->create(['room_type_id' => $old->room_type_id, 'status' => RoomStatus::Available]);
        $booking = $this->stay($old);

        $booking->changeRoom($new);

        $task = Task::query()->where('task_type', TaskType::FromBooking)->where('source_type', Room::class)->sole();
        $this->assertSame(Room::class, $task->source_type);
        $this->assertSame($old->id, $task->source_id);
        $this->assertSame(RoomStatus::NeedsCleaning, $old->fresh()->status);

        $task->update(['status' => TaskStatus::Done]);

        $this->assertSame(RoomStatus::Available, $old->fresh()->status);
        $this->assertSame(RoomStatus::Occupied, $new->fresh()->status);
    }

    public function test_the_rack_button_also_closes_the_open_cleaning_task(): void
    {
        $room = Room::factory()->create(['status' => RoomStatus::Occupied]);
        $task = $this->stay($room)->checkOut();

        Livewire::actingAs($this->admin())->test(RoomRack::class)->call('markReady', $room->id);

        $this->assertSame(RoomStatus::Available, $room->fresh()->status);
        $this->assertSame(TaskStatus::Done, $task->fresh()->status);
        $this->assertNotNull($task->fresh()->completed_at);
    }

    public function test_marking_a_room_cleaned_leaves_unrelated_tasks_alone(): void
    {
        $room = Room::factory()->create(['status' => RoomStatus::NeedsCleaning]);
        $other = Room::factory()->create(['status' => RoomStatus::NeedsCleaning]);
        $mine = $this->stay($room, BookingStatus::CheckedOut)->checkOut();
        $theirs = $this->stay($other, BookingStatus::CheckedOut)->checkOut();

        $room->markCleaned();

        $this->assertSame(TaskStatus::Done, $mine->fresh()->status);
        $this->assertSame(TaskStatus::Todo, $theirs->fresh()->status);
        $this->assertSame(RoomStatus::NeedsCleaning, $other->fresh()->status);
    }
}
