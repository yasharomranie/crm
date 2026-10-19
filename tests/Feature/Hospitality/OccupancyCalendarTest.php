<?php

namespace Tests\Feature\Hospitality;

use App\Domain\Hospitality\OccupancyCalendar;
use App\Enums\BookingStatus;
use App\Filament\Pages\OccupancyCalendarPage;
use App\Filament\Resources\Bookings\Pages\CreateBooking;
use App\Models\Booking;
use App\Models\Guest;
use App\Models\Room;
use App\Models\RoomType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class OccupancyCalendarTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $admin = User::factory()->create();
        $admin->assignRole(Role::firstOrCreate(['name' => 'admin']));

        return $admin;
    }

    private function booking(Room $room, string $in, string $out, BookingStatus $status = BookingStatus::Confirmed, ?string $guestName = null): Booking
    {
        return Booking::factory()->create([
            'room_id' => $room->id,
            'check_in_date' => $in,
            'check_out_date' => $out,
            'status' => $status,
            'guest_id' => Guest::factory()->create($guestName ? ['name' => $guestName] : [])->id,
        ]);
    }

    public function test_each_room_row_has_free_nights_and_booking_bars_with_their_span(): void
    {
        $room = Room::factory()->create(['name' => 'ویلا ۱']);
        $this->booking($room, '2030-03-03', '2030-03-06');

        $calendar = app(OccupancyCalendar::class)->build(Carbon::parse('2030-03-01'), 7);

        $cells = $calendar['rows'][0]['cells'];
        $this->assertSame(['free', 'free', 'booking', 'free', 'free'], array_column($cells, 'type'));
        $this->assertSame(3, $cells[2]['span']);
        $this->assertFalse($cells[2]['starts_before']);
        $this->assertFalse($cells[2]['ends_after']);
        $this->assertSame([0, 0, 1, 1, 1, 0, 0], array_column($calendar['days'], 'occupied'));
        $this->assertSame(1, $calendar['rooms']);
    }

    public function test_a_stay_crossing_the_window_edges_is_clipped_and_flagged(): void
    {
        $room = Room::factory()->create();
        $this->booking($room, '2030-02-27', '2030-03-03');
        $this->booking(Room::factory()->create(), '2030-03-06', '2030-03-12');

        $calendar = app(OccupancyCalendar::class)->build(Carbon::parse('2030-03-01'), 7);

        $first = $calendar['rows'][0]['cells'][0];
        $this->assertSame('booking', $first['type']);
        $this->assertSame(2, $first['span']);
        $this->assertTrue($first['starts_before']);
        $this->assertFalse($first['ends_after']);

        $second = end($calendar['rows'][1]['cells']);
        $this->assertSame(2, $second['span']);
        $this->assertTrue($second['ends_after']);
    }

    public function test_cancelled_bookings_inactive_rooms_and_other_types_are_left_out(): void
    {
        $type = RoomType::factory()->create();
        $other = RoomType::factory()->create();
        $room = Room::factory()->create(['room_type_id' => $type->id]);
        Room::factory()->create(['room_type_id' => $other->id]);
        Room::factory()->create(['room_type_id' => $type->id, 'is_active' => false]);
        $this->booking($room, '2030-03-03', '2030-03-05', BookingStatus::Cancelled);

        $calendar = app(OccupancyCalendar::class)->build(Carbon::parse('2030-03-01'), 7, $type->id);

        $this->assertSame(1, $calendar['rooms']);
        $this->assertSame(['free'], array_unique(array_column($calendar['rows'][0]['cells'], 'type')));
    }

    public function test_back_to_back_stays_sit_next_to_each_other(): void
    {
        $room = Room::factory()->create();
        $this->booking($room, '2030-03-01', '2030-03-03');
        $this->booking($room, '2030-03-03', '2030-03-05');

        $cells = app(OccupancyCalendar::class)->build(Carbon::parse('2030-03-01'), 5)['rows'][0]['cells'];

        $this->assertSame(['booking', 'booking', 'free'], array_column($cells, 'type'));
        $this->assertSame([2, 2], [$cells[0]['span'], $cells[1]['span']]);
    }

    public function test_the_page_shows_guests_navigates_and_filters(): void
    {
        $room = Room::factory()->create(['name' => 'ویلا شمالی']);
        $this->booking($room, today()->addDay()->toDateString(), today()->addDays(3)->toDateString(), BookingStatus::Confirmed, 'علی تقویمی');

        $page = Livewire::actingAs($this->admin())
            ->test(OccupancyCalendarPage::class)
            ->assertSee('ویلا شمالی')
            ->assertSee('علی تقویمی');

        $page->call('shift', 1)->assertSet('startDate', today()->addDays(7)->toDateString());
        $page->call('goToToday')->assertSet('startDate', today()->toDateString());
        $page->set('days', 30)->set('roomTypeId', (string) $room->room_type_id)->assertSee('ویلا شمالی');
        $page->set('roomTypeId', '')->assertSee('ویلا شمالی');
    }

    public function test_the_page_is_forbidden_without_permission(): void
    {
        $user = User::factory()->create();
        $user->assignRole(Role::create(['name' => 'بدون دسترسی تقویم']));

        Livewire::actingAs($user)->test(OccupancyCalendarPage::class)->assertForbidden();
    }

    public function test_a_free_night_opens_the_new_booking_form_with_room_and_dates_filled(): void
    {
        $room = Room::factory()->create();

        Livewire::actingAs($this->admin())
            ->withQueryParams(['room_id' => $room->id, 'check_in_date' => '2030-05-10', 'check_out_date' => '2030-05-11'])
            ->test(CreateBooking::class)
            ->assertFormSet(['room_id' => $room->id, 'check_in_date' => '2030-05-10', 'check_out_date' => '2030-05-11']);
    }
}
