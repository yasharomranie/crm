<?php

namespace Tests\Feature\Hospitality;

use App\Filament\Pages\RoomRack;
use App\Filament\Pages\TodayArrivals;
use App\Filament\Resources\Bookings\BookingResource;
use App\Filament\Resources\Bookings\Pages\CreateBooking;
use App\Filament\Resources\Bookings\Pages\ListBookings;
use App\Filament\Resources\RoomBlocks\Pages\ManageRoomBlocks;
use App\Filament\Support\PageHelp;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PageHelpTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $admin = User::factory()->create();
        $admin->assignRole(Role::firstOrCreate(['name' => 'admin']));

        return $admin;
    }

    public function test_every_mapped_screen_has_complete_copy_that_points_at_a_real_guide_section(): void
    {
        $guide = file_get_contents(resource_path('views/system-guide.blade.php'));

        foreach (array_unique(PageHelp::PAGES) as $key) {
            $entry = trans("page_help.{$key}");

            $this->assertIsArray($entry, "No help copy for {$key}");
            $this->assertNotEmpty($entry['title'], $key);
            $this->assertGreaterThanOrEqual(2, count($entry['steps']), $key);
            $this->assertStringContainsString('id="'.$entry['guide'].'"', $guide, "{$key} links to a missing guide section {$entry['guide']}");
        }
    }

    public function test_list_pages_get_their_resources_help_and_other_pages_none(): void
    {
        $this->assertSame('bookings', PageHelp::forComponent(ListBookings::class)['key']);
        $this->assertSame('room-blocks', PageHelp::forComponent(ManageRoomBlocks::class)['key']);
        $this->assertSame('room-rack', PageHelp::forComponent(RoomRack::class)['key']);
        $this->assertNull(PageHelp::forComponent(CreateBooking::class));
        $this->assertNull(PageHelp::forComponent('App\\Nothing\\Here'));
        $this->assertSame(BookingResource::class, array_search('bookings', PageHelp::PAGES, true));
    }

    public function test_the_card_shows_on_a_custom_page_and_on_a_resource_list(): void
    {
        $this->actingAs($this->admin())
            ->get(RoomRack::getUrl())
            ->assertOk()
            ->assertSee('راهنمای ساده‌ی این صفحه')
            ->assertSee('data-page-help="room-rack"', false)
            ->assertSee('/system-guide#ch-02-room-rack', false);

        $this->actingAs($this->admin())
            ->get(ListBookings::getUrl())
            ->assertOk()
            ->assertSee('data-page-help="bookings"', false);

        $this->actingAs($this->admin())
            ->get(TodayArrivals::getUrl())
            ->assertOk()
            ->assertSee('data-page-help="today-arrivals"', false);
    }

    public function test_a_screen_without_copy_has_no_card(): void
    {
        $this->actingAs($this->admin())
            ->get(CreateBooking::getUrl())
            ->assertOk()
            ->assertDontSee('data-page-help', false);
    }
}
