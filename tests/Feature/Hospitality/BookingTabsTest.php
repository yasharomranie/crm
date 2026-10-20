<?php

namespace Tests\Feature\Hospitality;

use App\Enums\BookingStatus;
use App\Filament\Resources\Bookings\Pages\ListBookings;
use App\Models\Booking;
use App\Models\Room;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class BookingTabsTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $admin = User::factory()->create();
        $admin->assignRole(Role::firstOrCreate(['name' => 'admin']));

        return $admin;
    }

    private function booking(BookingStatus $status, int $fromDay, int $toDay): Booking
    {
        return Booking::factory()->create([
            'room_id' => Room::factory()->create()->id,
            'status' => $status,
            'check_in_date' => today()->addDays($fromDay)->toDateString(),
            'check_out_date' => today()->addDays($toDay)->toDateString(),
        ]);
    }

    public function test_each_tab_shows_only_its_bookings(): void
    {
        $arrivalToday = $this->booking(BookingStatus::Confirmed, 0, 2);
        $inHouse = $this->booking(BookingStatus::CheckedIn, -1, 2);
        $upcoming = $this->booking(BookingStatus::Confirmed, 3, 5);
        $pending = $this->booking(BookingStatus::Pending, 4, 6);
        $cancelled = $this->booking(BookingStatus::Cancelled, 1, 3);

        $page = fn (string $tab) => Livewire::actingAs($this->admin())->test(ListBookings::class)->set('activeTab', $tab);

        $page('all')->assertCanSeeTableRecords([$arrivalToday, $inHouse, $upcoming, $pending, $cancelled]);
        $page('arrivals_today')->assertCanSeeTableRecords([$arrivalToday])->assertCanNotSeeTableRecords([$inHouse, $upcoming, $pending, $cancelled]);
        $page('in_house')->assertCanSeeTableRecords([$inHouse])->assertCanNotSeeTableRecords([$arrivalToday, $upcoming, $pending, $cancelled]);
        $page('upcoming')->assertCanSeeTableRecords([$upcoming, $pending])->assertCanNotSeeTableRecords([$arrivalToday, $inHouse, $cancelled]);
        $page('pending')->assertCanSeeTableRecords([$pending])->assertCanNotSeeTableRecords([$arrivalToday, $inHouse, $upcoming, $cancelled]);
        $page('cancelled')->assertCanSeeTableRecords([$cancelled])->assertCanNotSeeTableRecords([$arrivalToday, $inHouse, $upcoming, $pending]);
    }

    public function test_the_list_opens_on_all_bookings(): void
    {
        $component = Livewire::actingAs($this->admin())->test(ListBookings::class);

        $this->assertSame('all', $component->instance()->activeTab);
    }
}
