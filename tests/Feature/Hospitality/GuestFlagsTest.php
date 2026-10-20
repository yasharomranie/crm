<?php

namespace Tests\Feature\Hospitality;

use App\Enums\BookingStatus;
use App\Exceptions\GuestBlockedException;
use App\Filament\Resources\Bookings\Pages\ListBookings;
use App\Models\Booking;
use App\Models\Guest;
use App\Models\Room;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class GuestFlagsTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $admin = User::factory()->create();
        $admin->assignRole(Role::firstOrCreate(['name' => 'admin']));

        return $admin;
    }

    private function bookingFor(Guest $guest): Booking
    {
        return Booking::factory()->create([
            'guest_id' => $guest->id,
            'room_id' => Room::factory()->create()->id,
            'status' => BookingStatus::Confirmed,
            'check_in_date' => now()->addDays(3)->toDateString(),
            'check_out_date' => now()->addDays(5)->toDateString(),
        ]);
    }

    public function test_a_blocked_guest_cannot_get_a_new_booking(): void
    {
        $guest = Guest::factory()->create(['is_blocked' => true]);

        $this->expectException(GuestBlockedException::class);

        $this->bookingFor($guest);
    }

    public function test_unblocking_allows_bookings_again_and_old_bookings_survive_blocking(): void
    {
        $guest = Guest::factory()->create();
        $existing = $this->bookingFor($guest);

        $guest->update(['is_blocked' => true]);
        $this->assertSame(BookingStatus::Confirmed, $existing->fresh()->status);

        $guest->update(['is_blocked' => false]);
        $this->assertNotNull($this->bookingFor($guest)->id);
    }

    public function test_the_check_in_confirmation_shows_the_reception_alert(): void
    {
        $guest = Guest::factory()->create(['reception_alert' => 'مهمان ویژه - هدیه خوش‌آمد']);
        $booking = $this->bookingFor($guest);

        $component = Livewire::actingAs($this->admin())
            ->test(ListBookings::class)
            ->mountTableAction('checkIn', $booking);

        $this->assertStringContainsString(
            'مهمان ویژه - هدیه خوش‌آمد',
            (string) $component->instance()->getMountedTableAction()->getModalDescription(),
        );
    }

    public function test_the_bookings_list_marks_guests_with_an_alert_or_a_block(): void
    {
        $alerted = $this->bookingFor(Guest::factory()->create(['reception_alert' => 'مهمان ویژه']));
        $plain = $this->bookingFor(Guest::factory()->create());

        Livewire::actingAs($this->admin())
            ->test(ListBookings::class)
            ->assertTableColumnStateSet('guest_flag', true, $alerted)
            ->assertTableColumnStateSet('guest_flag', false, $plain);
    }
}
