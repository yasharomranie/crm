<?php

namespace Tests\Feature\Hospitality;

use App\Filament\Resources\Bookings\Pages\ListBookings;
use App\Models\Booking;
use App\Models\Guest;
use App\Models\Room;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class BookingSearchTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $admin = User::factory()->create();
        $admin->assignRole(Role::firstOrCreate(['name' => 'admin']));

        return $admin;
    }

    private function bookingFor(array $guest, string $roomName): Booking
    {
        return Booking::factory()->create([
            'guest_id' => Guest::factory()->create($guest)->id,
            'room_id' => Room::factory()->create(['name' => $roomName])->id,
        ]);
    }

    public function test_a_booking_is_found_by_name_mobile_in_any_spelling_national_code_or_room(): void
    {
        $target = $this->bookingFor(['name' => 'سارا کریمی', 'mobile' => '09121234567', 'national_code' => '0012345678'], 'ویلا نخل');
        $other = $this->bookingFor(['name' => 'علی رضایی', 'mobile' => '09350000000', 'national_code' => '0099999999'], 'ویلا سرو');

        foreach (['کریمی', '09121234567', '+989121234567', '۰۹۱۲۱۲۳۴۵۶۷', '0012345678', 'نخل'] as $search) {
            Livewire::actingAs($this->admin())
                ->test(ListBookings::class)
                ->searchTable($search)
                ->assertCanSeeTableRecords([$target])
                ->assertCanNotSeeTableRecords([$other]);
        }
    }
}
