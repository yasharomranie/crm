<?php

namespace Tests\Feature;

use App\Domain\System\DemoDataGenerator;
use App\Enums\BookingStatus;
use App\Filament\Pages\RoomRack;
use App\Filament\Pages\TestDataTools;
use App\Models\Booking;
use App\Models\Company;
use App\Models\Deal;
use App\Models\Guest;
use App\Models\Room;
use App\Models\RoomBlock;
use App\Models\RoomType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * The marked sample data for trying the screens on the live server: it must
 * be complete, usable by the real screens, and never touch what was already there.
 */
class DemoDataGeneratorTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $admin = User::factory()->create();
        $admin->assignRole(Role::firstOrCreate(['name' => 'admin']));

        return $admin;
    }

    public function test_it_creates_the_marked_records_with_their_folios(): void
    {
        $result = app(DemoDataGenerator::class)->create();

        $this->assertTrue($result['created']);
        $this->assertSame(
            ['room_types' => 1, 'rooms' => 3, 'guests' => 3, 'bookings' => 4, 'blocks' => 1, 'companies' => 1, 'deals' => 2],
            $result['counts'],
        );

        $statuses = Booking::query()->pluck('status')->map->value->sort()->values()->all();
        $this->assertSame(['checked_in', 'checked_out', 'confirmed', 'pending'], $statuses);

        Booking::with('charges')->get()->each(fn (Booking $booking) => $this->assertGreaterThan(0, $booking->charges->count()));
        $this->assertNull(RoomBlock::sole()->ends_on);
    }

    public function test_the_live_screens_work_with_the_sample_data(): void
    {
        app(DemoDataGenerator::class)->create();

        Livewire::actingAs($this->admin())
            ->test(RoomRack::class)
            ->assertOk()
            ->assertSee(DemoDataGenerator::MARK.'اتاق ۱')
            ->assertSee('تا اطلاع بعدی');
    }

    public function test_it_never_changes_records_that_were_already_there(): void
    {
        $type = RoomType::factory()->create(['name' => 'سوئیت واقعی']);
        $room = Room::factory()->create(['name' => 'ویلا واقعی', 'room_type_id' => $type->id]);
        $guest = Guest::factory()->create(['name' => 'مهمان واقعی']);
        $booking = Booking::factory()->create(['room_id' => $room->id, 'guest_id' => $guest->id, 'status' => BookingStatus::Confirmed]);
        $company = Company::factory()->create(['name' => 'شرکت واقعی']);

        $before = [
            'rooms' => Room::count(), 'guests' => Guest::count(), 'bookings' => Booking::count(),
            'companies' => Company::count(), 'room_types' => RoomType::count(),
        ];

        $generator = app(DemoDataGenerator::class);
        $generator->create();
        $generator->remove();

        $this->assertSame($before, [
            'rooms' => Room::count(), 'guests' => Guest::count(), 'bookings' => Booking::count(),
            'companies' => Company::count(), 'room_types' => RoomType::count(),
        ]);
        $this->assertTrue($booking->fresh()->is($booking));
        $this->assertSame('ویلا واقعی', $room->fresh()->name);
        $this->assertSame('شرکت واقعی', $company->fresh()->name);
        $this->assertSame(0, Deal::where('title', 'like', DemoDataGenerator::MARK.'%')->count());
    }

    public function test_it_does_not_create_the_sample_data_twice(): void
    {
        $generator = app(DemoDataGenerator::class);
        $generator->create();

        $this->assertFalse($generator->create()['created']);
        $this->assertSame(3, Room::count());
    }

    public function test_removing_clears_everything_it_made(): void
    {
        $generator = app(DemoDataGenerator::class);
        $generator->create();
        $result = $generator->remove();

        $this->assertSame(0, array_sum($generator->counts()));
        $this->assertSame(0, Booking::count());
        $this->assertSame(0, $result['kept_rooms']);
        $this->assertFalse($generator->exists());
    }

    public function test_a_sample_room_with_a_real_booking_is_kept(): void
    {
        $generator = app(DemoDataGenerator::class);
        $generator->create();

        $room = Room::where('name', DemoDataGenerator::MARK.'اتاق ۲')->firstOrFail();
        $realGuest = Guest::factory()->create(['name' => 'مهمان واقعی']);
        Booking::factory()->create([
            'room_id' => $room->id,
            'guest_id' => $realGuest->id,
            'check_in_date' => today()->addDays(30)->toDateString(),
            'check_out_date' => today()->addDays(32)->toDateString(),
        ]);

        $result = $generator->remove();

        $this->assertSame(1, $result['kept_rooms']);
        $this->assertTrue(Room::whereKey($room->id)->exists());
        $this->assertSame(1, Booking::count());
    }

    public function test_the_page_creates_and_removes_through_its_buttons(): void
    {
        $page = Livewire::actingAs($this->admin())->test(TestDataTools::class);

        $page->callAction('createTestData')->assertNotified('دادهٔ آزمایشی ساخته شد.');
        $this->assertSame(3, Room::count());

        $page->callAction('removeTestData')->assertNotified('دادهٔ آزمایشی پاک شد.');
        $this->assertSame(0, Room::count());
    }

    public function test_only_users_with_the_permission_can_open_the_page(): void
    {
        $this->artisan('access-control:sync');

        $role = Role::create(['name' => 'بدون دادهٔ آزمایشی']);
        $role->givePermissionTo(Permission::where('name', 'ai-assistant.access')->first());
        $limited = User::factory()->create();
        $limited->assignRole($role);

        $this->actingAs($limited);
        $this->assertFalse(TestDataTools::canAccess());

        $this->actingAs($this->admin());
        $this->assertTrue(TestDataTools::canAccess());
    }
}
