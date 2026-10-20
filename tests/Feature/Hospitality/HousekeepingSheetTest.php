<?php

namespace Tests\Feature\Hospitality;

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\Guest;
use App\Models\Room;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class HousekeepingSheetTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $admin = User::factory()->create();
        $admin->assignRole(Role::firstOrCreate(['name' => 'admin']));

        return $admin;
    }

    private function stay(string $roomName, string $guestName, BookingStatus $status, int $fromDay, int $toDay): Booking
    {
        return Booking::factory()->create([
            'room_id' => Room::factory()->create(['name' => $roomName])->id,
            'guest_id' => Guest::factory()->create(['name' => $guestName])->id,
            'status' => $status,
            'check_in_date' => today()->addDays($fromDay)->toDateString(),
            'check_out_date' => today()->addDays($toDay)->toDateString(),
        ]);
    }

    public function test_the_sheet_lists_departures_arrivals_and_stayovers(): void
    {
        $this->stay('واحد خروجی', 'مهمان خروجی', BookingStatus::CheckedIn, -2, 0);
        $this->stay('واحد ورودی', 'مهمان ورودی', BookingStatus::Confirmed, 0, 2);
        $this->stay('واحد مقیم', 'مهمان مقیم', BookingStatus::CheckedIn, -1, 3);
        $this->stay('واحد فردایی', 'مهمان فردایی', BookingStatus::Confirmed, 1, 3);

        $this->actingAs($this->admin())
            ->get(route('housekeeping-sheet'))
            ->assertOk()
            ->assertSee('واحد خروجی')
            ->assertSee('واحد ورودی')
            ->assertSee('واحد مقیم')
            ->assertDontSee('واحد فردایی');
    }

    public function test_another_day_can_be_requested(): void
    {
        $this->stay('واحد فردایی', 'مهمان فردایی', BookingStatus::Confirmed, 1, 3);

        $this->actingAs($this->admin())
            ->get(route('housekeeping-sheet', ['date' => today()->addDay()->toDateString()]))
            ->assertOk()
            ->assertSee('واحد فردایی');
    }

    public function test_a_bad_date_is_rejected(): void
    {
        $this->actingAs($this->admin())
            ->get(route('housekeeping-sheet', ['date' => 'نامعتبر']))
            ->assertSessionHasErrors('date');
    }

    public function test_staff_without_the_permission_are_forbidden(): void
    {
        $user = User::factory()->create();
        $user->assignRole(Role::create(['name' => 'بدون دسترسی برگه نظافت']));

        $this->actingAs($user)->get(route('housekeeping-sheet'))->assertForbidden();
    }

    public function test_guests_are_sent_to_login(): void
    {
        $this->get(route('housekeeping-sheet'))->assertRedirect();
    }
}
