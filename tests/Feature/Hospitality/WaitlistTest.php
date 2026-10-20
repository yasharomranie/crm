<?php

namespace Tests\Feature\Hospitality;

use App\Enums\BookingStatus;
use App\Filament\Resources\WaitlistEntries\Pages\ManageWaitlistEntries;
use App\Jobs\NotifyWaitlistJob;
use App\Models\Booking;
use App\Models\CustomerMessage;
use App\Models\Guest;
use App\Models\Room;
use App\Models\RoomType;
use App\Models\SmsSetting;
use App\Models\User;
use App\Models\WaitlistEntry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class WaitlistTest extends TestCase
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

    private function booking(Room $room, int $fromDay, int $toDay): Booking
    {
        return Booking::factory()->create([
            'room_id' => $room->id,
            'status' => BookingStatus::Confirmed,
            'check_in_date' => now()->addDays($fromDay)->toDateString(),
            'check_out_date' => now()->addDays($toDay)->toDateString(),
        ]);
    }

    private function entry(?RoomType $type, int $fromDay, int $toDay, array $overrides = []): WaitlistEntry
    {
        return WaitlistEntry::factory()->create($overrides + [
            'room_type_id' => $type?->id,
            'check_in_date' => now()->addDays($fromDay)->toDateString(),
            'check_out_date' => now()->addDays($toDay)->toDateString(),
        ]);
    }

    public function test_cancelling_the_booking_that_blocked_a_waiting_guest_texts_them_once(): void
    {
        $this->smsReady();
        $room = Room::factory()->create();
        $booking = $this->booking($room, 10, 14);
        $entry = $this->entry($room->roomType, 10, 14);

        $booking->cancel('لغو', false, true);

        $entry->refresh();
        $this->assertSame(WaitlistEntry::STATUS_NOTIFIED, $entry->status);
        $this->assertNotNull($entry->notified_at);
        $this->assertSame(1, CustomerMessage::where('guest_id', $entry->guest_id)->count());

        // Another room freed for the same nights does not text the same guest again.
        $second = Room::factory()->create(['room_type_id' => $room->room_type_id]);
        $this->booking($second, 10, 14)->cancel('لغو', false, true);
        $this->assertSame(1, CustomerMessage::where('guest_id', $entry->guest_id)->count());
    }

    public function test_an_entry_with_no_preferred_type_is_notified_for_any_room(): void
    {
        $this->smsReady();
        $room = Room::factory()->create();
        $booking = $this->booking($room, 10, 14);
        $entry = $this->entry(null, 11, 13);

        $booking->cancel(null, false, true);

        $this->assertSame(WaitlistEntry::STATUS_NOTIFIED, $entry->fresh()->status);
    }

    public function test_a_guest_wanting_another_room_type_is_left_alone(): void
    {
        $this->smsReady();
        $booking = $this->booking(Room::factory()->create(), 10, 14);
        $other = $this->entry(RoomType::factory()->create(), 10, 14);

        $booking->cancel(null, false, true);

        $this->assertSame(WaitlistEntry::STATUS_WAITING, $other->fresh()->status);
        $this->assertSame(0, CustomerMessage::count());
    }

    public function test_a_guest_is_not_told_while_part_of_their_nights_are_still_taken(): void
    {
        $this->smsReady();
        $room = Room::factory()->create();
        $first = $this->booking($room, 10, 12);
        $this->booking($room, 12, 14);
        $entry = $this->entry($room->roomType, 10, 14);

        $first->cancel(null, false, true);

        $this->assertSame(WaitlistEntry::STATUS_WAITING, $entry->fresh()->status);
    }

    public function test_past_and_unrelated_dates_are_ignored(): void
    {
        $this->smsReady();
        $room = Room::factory()->create();
        $booking = $this->booking($room, 10, 12);
        $unrelated = $this->entry($room->roomType, 30, 32);
        $past = $this->entry($room->roomType, -5, -3);

        $booking->cancel(null, false, true);

        $this->assertSame(WaitlistEntry::STATUS_WAITING, $unrelated->fresh()->status);
        $this->assertSame(WaitlistEntry::STATUS_WAITING, $past->fresh()->status);
    }

    public function test_a_failed_text_keeps_the_guest_waiting(): void
    {
        $room = Room::factory()->create();
        $booking = $this->booking($room, 10, 12);
        $entry = $this->entry($room->roomType, 10, 12);

        $booking->cancel(null, false, true);

        $this->assertSame(WaitlistEntry::STATUS_WAITING, $entry->fresh()->status);
        $this->assertNull($entry->fresh()->notified_at);
    }

    public function test_nothing_is_queued_when_nobody_is_waiting(): void
    {
        Queue::fake();
        $booking = $this->booking(Room::factory()->create(), 10, 12);

        $booking->cancel(null, false, true);

        Queue::assertNotPushed(NotifyWaitlistJob::class);
    }

    public function test_the_page_lists_entries_notifies_by_hand_and_closes_them(): void
    {
        $this->smsReady();
        $room = Room::factory()->create();
        $guest = Guest::factory()->create(['name' => 'منتظر تستی']);
        $entry = $this->entry($room->roomType, 10, 12, ['guest_id' => $guest->id]);
        $closing = $this->entry($room->roomType, 20, 22);
        $booked = $this->entry($room->roomType, 30, 32);

        Livewire::actingAs($this->admin())
            ->test(ManageWaitlistEntries::class)
            ->assertCanSeeTableRecords([$entry, $closing, $booked])
            ->assertSee('منتظر تستی')
            ->callTableAction('notifyGuest', $entry)
            ->assertNotified('پیامک ارسال شد')
            ->callTableAction('closeEntry', $closing)
            ->callTableAction('markBooked', $booked);

        $this->assertSame(WaitlistEntry::STATUS_NOTIFIED, $entry->fresh()->status);
        $this->assertSame(WaitlistEntry::STATUS_CLOSED, $closing->fresh()->status);
        $this->assertSame(WaitlistEntry::STATUS_BOOKED, $booked->fresh()->status);
    }

    public function test_the_page_is_forbidden_without_permission(): void
    {
        $user = User::factory()->create();
        $user->assignRole(Role::create(['name' => 'بدون دسترسی انتظار']));

        Livewire::actingAs($user)->test(ManageWaitlistEntries::class)->assertForbidden();
    }
}
