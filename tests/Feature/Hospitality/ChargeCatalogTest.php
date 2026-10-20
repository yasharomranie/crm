<?php

namespace Tests\Feature\Hospitality;

use App\Enums\BookingStatus;
use App\Filament\Resources\Bookings\Pages\EditBooking;
use App\Filament\Resources\Bookings\Pages\ListBookings;
use App\Filament\Resources\Bookings\RelationManagers\ChargesRelationManager;
use App\Filament\Resources\ChargeItems\Pages\ManageChargeItems;
use App\Models\Booking;
use App\Models\BookingCharge;
use App\Models\ChargeItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ChargeCatalogTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $admin = User::factory()->create();
        $admin->assignRole(Role::firstOrCreate(['name' => 'admin']));

        return $admin;
    }

    private function booking(BookingStatus $status = BookingStatus::CheckedIn): Booking
    {
        $booking = Booking::factory()->create(['status' => $status]);
        $booking->charges()->delete();
        BookingCharge::query()->create(['booking_id' => $booking->id, 'title' => 'اقامت', 'quantity' => 1, 'unit_amount' => 1_000_000]);

        return $booking->fresh();
    }

    public function test_adding_an_item_writes_its_name_and_price_onto_the_folio(): void
    {
        $item = ChargeItem::factory()->create(['name' => 'صبحانه', 'unit_amount' => 150_000]);
        $booking = $this->booking();

        $charge = $item->addTo($booking, 3);

        $this->assertSame('صبحانه', $charge->title);
        $this->assertSame(3, $charge->quantity);
        $this->assertSame(1_450_000, $booking->fresh()->totalAmount());
        $this->assertSame(1_450_000, $booking->fresh(['charges', 'payments'])->balanceDue());
    }

    public function test_changing_the_catalog_later_never_changes_a_written_bill(): void
    {
        $item = ChargeItem::factory()->create(['name' => 'شام', 'unit_amount' => 300_000]);
        $booking = $this->booking();
        $item->addTo($booking);

        $item->update(['name' => 'شام ویژه', 'unit_amount' => 900_000]);

        $line = $booking->fresh()->charges()->where('title', 'شام')->sole();
        $this->assertSame(300_000, (int) $line->unit_amount);
        $this->assertSame(1_300_000, $booking->fresh()->totalAmount());
    }

    public function test_the_quantity_is_at_least_one(): void
    {
        $item = ChargeItem::factory()->create(['unit_amount' => 100_000]);

        $this->assertSame(1, $item->addTo($this->booking(), 0)->quantity);
    }

    public function test_the_bookings_list_action_adds_an_active_item_and_hides_for_finished_stays(): void
    {
        $item = ChargeItem::factory()->create(['name' => 'لباسشویی', 'unit_amount' => 80_000]);
        $booking = $this->booking();
        $cancelled = $this->booking(BookingStatus::Cancelled);

        Livewire::actingAs($this->admin())
            ->test(ListBookings::class)
            ->assertTableActionVisible('addCatalogCharge', $booking)
            ->assertTableActionHidden('addCatalogCharge', $cancelled)
            ->callTableAction('addCatalogCharge', $booking, data: ['charge_item_id' => $item->id, 'quantity' => 2])
            ->assertNotified('به صورت‌حساب اضافه شد');

        $this->assertSame(1_160_000, $booking->fresh()->totalAmount());
    }

    public function test_the_action_is_hidden_when_nothing_is_in_the_catalog(): void
    {
        $booking = $this->booking();

        Livewire::actingAs($this->admin())
            ->test(ListBookings::class)
            ->assertTableActionHidden('addCatalogCharge', $booking);
    }

    public function test_an_inactive_item_cannot_be_chosen(): void
    {
        ChargeItem::factory()->create(['name' => 'فعال']);
        $inactive = ChargeItem::factory()->create(['name' => 'خاموش', 'is_active' => false]);
        $booking = $this->booking();

        Livewire::actingAs($this->admin())
            ->test(ListBookings::class)
            ->callTableAction('addCatalogCharge', $booking, data: ['charge_item_id' => $inactive->id, 'quantity' => 1])
            ->assertHasTableActionErrors(['charge_item_id']);

        $this->assertSame(1, $booking->fresh()->charges()->count());
    }

    public function test_the_folio_panel_of_a_booking_offers_the_catalog_too(): void
    {
        $item = ChargeItem::factory()->create(['name' => 'مینی‌بار', 'unit_amount' => 40_000]);
        $booking = $this->booking();

        Livewire::actingAs($this->admin())
            ->test(ChargesRelationManager::class, ['ownerRecord' => $booking, 'pageClass' => EditBooking::class])
            ->callTableAction('addCatalogCharge', data: ['charge_item_id' => $item->id, 'quantity' => 1])
            ->assertNotified('به صورت‌حساب اضافه شد');

        $this->assertSame(1_040_000, $booking->fresh()->totalAmount());
    }

    public function test_the_catalog_page_lists_items(): void
    {
        ChargeItem::factory()->create(['name' => 'قلیان']);

        Livewire::actingAs($this->admin())->test(ManageChargeItems::class)->assertSuccessful()->assertSee('قلیان');
    }
}
