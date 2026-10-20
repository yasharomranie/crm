<?php

namespace Tests\Feature\Hospitality;

use App\Domain\Hospitality\LostItemService;
use App\Filament\Resources\Bookings\Pages\ListBookings;
use App\Filament\Resources\LostItems\Pages\ManageLostItems;
use App\Models\Booking;
use App\Models\CustomerMessage;
use App\Models\Guest;
use App\Models\LostItem;
use App\Models\SmsSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class LostItemTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $admin = User::factory()->create();
        $admin->assignRole(Role::firstOrCreate(['name' => 'admin']));

        return $admin;
    }

    private function smsReady(): void
    {
        Http::fake(['api.sms.ir/*' => Http::response(['status' => 1])]);
        SmsSetting::factory()->create();
    }

    public function test_recording_from_a_booking_copies_its_guest_and_room(): void
    {
        $booking = Booking::factory()->create();

        $item = app(LostItemService::class)->recordForBooking($booking, 'شارژر سفید', 'کنار تخت');

        $this->assertSame($booking->guest_id, $item->guest_id);
        $this->assertSame($booking->room_id, $item->room_id);
        $this->assertSame(LostItem::STATUS_FOUND, $item->status);
        $this->assertTrue($item->isOpen());
    }

    public function test_telling_the_guest_texts_them_and_marks_the_item_contacted(): void
    {
        $this->smsReady();
        $item = LostItem::factory()->create(['guest_id' => Guest::factory()->create(['mobile' => '09120000000'])->id]);

        $this->assertTrue(app(LostItemService::class)->tellGuest($item));

        $item->refresh();
        $this->assertSame(LostItem::STATUS_CONTACTED, $item->status);
        $this->assertNotNull($item->contacted_at);
        $this->assertSame(1, CustomerMessage::query()->count());
    }

    public function test_a_failed_text_leaves_the_item_found(): void
    {
        $item = LostItem::factory()->create(['guest_id' => Guest::factory()->create(['mobile' => null])->id]);

        $this->assertFalse(app(LostItemService::class)->tellGuest($item));
        $this->assertSame(LostItem::STATUS_FOUND, $item->fresh()->status);
    }

    public function test_an_item_without_a_guest_cannot_be_announced(): void
    {
        $item = LostItem::factory()->create(['guest_id' => null]);

        $this->expectException(InvalidArgumentException::class);

        app(LostItemService::class)->tellGuest($item);
    }

    public function test_closing_records_the_outcome_and_only_once(): void
    {
        $item = LostItem::factory()->create();

        app(LostItemService::class)->close($item, LostItem::STATUS_RETURNED, 'تحویل به خود مهمان');

        $item->refresh();
        $this->assertSame(LostItem::STATUS_RETURNED, $item->status);
        $this->assertSame('تحویل به خود مهمان', $item->closing_note);
        $this->assertNotNull($item->closed_at);
        $this->assertFalse($item->isOpen());

        $this->expectException(InvalidArgumentException::class);

        app(LostItemService::class)->close($item, LostItem::STATUS_DISPOSED);
    }

    public function test_staff_record_an_item_from_the_bookings_list(): void
    {
        $booking = Booking::factory()->create();

        Livewire::actingAs($this->admin())
            ->test(ListBookings::class)
            ->callTableAction('recordLostItem', $booking, data: ['description' => 'عینک آفتابی', 'location' => 'تراس'])
            ->assertNotified('شیء جامانده ثبت شد');

        $item = LostItem::sole();
        $this->assertSame($booking->id, $item->booking_id);
        $this->assertSame('عینک آفتابی', $item->description);
    }

    public function test_staff_work_the_lost_items_list(): void
    {
        $this->smsReady();
        $guest = Guest::factory()->create(['mobile' => '09120000000']);
        $item = LostItem::factory()->create(['guest_id' => $guest->id]);
        $closed = LostItem::factory()->create(['status' => LostItem::STATUS_RETURNED]);

        Livewire::actingAs($this->admin())
            ->test(ManageLostItems::class)
            ->assertCanSeeTableRecords([$item, $closed])
            ->assertTableActionHidden('tellGuest', $closed)
            ->assertTableActionHidden('markReturned', $closed)
            ->callTableAction('tellGuest', $item)
            ->assertNotified('پیامک ارسال شد')
            ->callTableAction('markDisposed', $item, data: ['note' => 'بایگانی شد']);

        $this->assertSame(LostItem::STATUS_DISPOSED, $item->fresh()->status);
    }

    public function test_the_page_is_forbidden_without_permission(): void
    {
        $user = User::factory()->create();
        $user->assignRole(Role::create(['name' => 'بدون دسترسی جامانده']));

        Livewire::actingAs($user)->test(ManageLostItems::class)->assertForbidden();
    }
}
