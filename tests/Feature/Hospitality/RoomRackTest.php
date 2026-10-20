<?php

namespace Tests\Feature\Hospitality;

use App\Enums\BookingStatus;
use App\Enums\RoomStatus;
use App\Filament\Pages\RoomRack;
use App\Models\Booking;
use App\Models\Guest;
use App\Models\Room;
use App\Models\RoomBlock;
use App\Models\RoomType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class RoomRackTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $admin = User::factory()->create();
        $admin->assignRole(Role::firstOrCreate(['name' => 'admin']));

        return $admin;
    }

    private function room(string $name, RoomStatus $status = RoomStatus::Available, ?RoomType $type = null): Room
    {
        return Room::factory()->create(['name' => $name, 'status' => $status, 'room_type_id' => ($type ?? RoomType::factory()->create())->id]);
    }

    private function stay(Room $room, string $guest, BookingStatus $status, int $fromDay, int $toDay): Booking
    {
        return Booking::factory()->create([
            'room_id' => $room->id,
            'guest_id' => Guest::factory()->create(['name' => $guest])->id,
            'status' => $status,
            'check_in_date' => today()->addDays($fromDay)->toDateString(),
            'check_out_date' => today()->addDays($toDay)->toDateString(),
            'actual_check_in_at' => $status === BookingStatus::CheckedIn ? now()->addDays($fromDay) : null,
        ]);
    }

    public function test_every_active_unit_is_shown_with_its_guest(): void
    {
        $occupied = $this->room('ویلا یک', RoomStatus::Occupied);
        $this->stay($occupied, 'مهمان مقیم تستی', BookingStatus::CheckedIn, -1, 3);
        $free = $this->room('ویلا دو');
        $this->stay($free, 'مهمان بعدی تستی', BookingStatus::Confirmed, 2, 4);
        Room::factory()->create(['name' => 'ویلا غیرفعال', 'is_active' => false]);

        Livewire::actingAs($this->admin())
            ->test(RoomRack::class)
            ->assertSee('ویلا یک')
            ->assertSee('مهمان مقیم تستی')
            ->assertSee('ویلا دو')
            ->assertSee('مهمان بعدی تستی')
            ->assertDontSee('ویلا غیرفعال');
    }

    public function test_counts_follow_the_real_state_of_the_units(): void
    {
        $this->stay($this->room('الف', RoomStatus::Occupied), 'الف مهمان', BookingStatus::CheckedIn, -2, 0);
        $this->stay($this->room('ب'), 'ب مهمان', BookingStatus::Confirmed, 0, 2);
        $this->room('ج', RoomStatus::NeedsCleaning);
        $blocked = $this->room('د');
        RoomBlock::factory()->create(['room_id' => $blocked->id, 'starts_on' => today()->subDay()->toDateString(), 'ends_on' => today()->addDays(2)->toDateString()]);

        $counts = Livewire::actingAs($this->admin())->test(RoomRack::class)->instance()->getCounts();

        $this->assertSame(4, $counts['all']);
        $this->assertSame(1, $counts['occupied']);
        $this->assertSame(1, $counts['needs_cleaning']);
        $this->assertSame(1, $counts['out_of_service']);
        $this->assertSame(1, $counts['available']);
        $this->assertSame(1, $counts['arriving']);
        $this->assertSame(1, $counts['departing']);
    }

    public function test_the_status_chips_and_the_search_narrow_the_board(): void
    {
        $this->room('اتاق تمیز');
        $this->room('اتاق کثیف', RoomStatus::NeedsCleaning);

        Livewire::actingAs($this->admin())
            ->test(RoomRack::class)
            ->call('setFilter', 'needs_cleaning')
            ->assertSee('اتاق کثیف')
            ->assertDontSee('اتاق تمیز')
            ->call('setFilter', 'all')
            ->set('search', 'تمیز')
            ->assertSee('اتاق تمیز')
            ->assertDontSee('اتاق کثیف');
    }

    public function test_a_unit_can_be_found_by_its_guest(): void
    {
        $this->stay($this->room('ویلا الف', RoomStatus::Occupied), 'سارا کریمی', BookingStatus::CheckedIn, -1, 2);
        $this->room('ویلا ب');

        Livewire::actingAs($this->admin())
            ->test(RoomRack::class)
            ->set('search', 'کریمی')
            ->assertSee('ویلا الف')
            ->assertDontSee('ویلا ب');
    }

    public function test_housekeeping_puts_a_unit_back_on_sale(): void
    {
        $room = $this->room('ویلا نظافت', RoomStatus::NeedsCleaning);

        Livewire::actingAs($this->admin())
            ->test(RoomRack::class)
            ->call('markReady', $room->id)
            ->assertNotified();

        $this->assertSame(RoomStatus::Available, $room->fresh()->status);
    }

    public function test_only_a_unit_that_needs_cleaning_can_be_marked_ready(): void
    {
        $room = $this->room('ویلا مقیم', RoomStatus::Occupied);

        Livewire::actingAs($this->admin())->test(RoomRack::class)->call('markReady', $room->id);

        $this->assertSame(RoomStatus::Occupied, $room->fresh()->status);
    }

    public function test_the_page_is_forbidden_without_permission(): void
    {
        $user = User::factory()->create();
        $user->assignRole(Role::create(['name' => 'بدون دسترسی رک']));

        Livewire::actingAs($user)->test(RoomRack::class)->assertForbidden();
    }
}
