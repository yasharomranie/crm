<?php

namespace Tests\Feature\Hospitality;

use App\Enums\BookingStatus;
use App\Enums\SalesChannelType;
use App\Exceptions\ChannelAllotmentExceededException;
use App\Exceptions\RoomNotAvailableException;
use App\Filament\Pages\TodayArrivals;
use App\Models\Booking;
use App\Models\ChannelAllotment;
use App\Models\ExternalApiClient;
use App\Models\Guest;
use App\Models\Room;
use App\Models\RoomType;
use App\Models\SalesChannel;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ChannelAllotmentTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $admin = User::factory()->create();
        $admin->assignRole(Role::firstOrCreate(['name' => 'admin']));

        return $admin;
    }

    private function channel(string $name = 'اسنپ‌تریپ'): SalesChannel
    {
        return SalesChannel::query()->create(['name' => $name, 'type' => SalesChannelType::Ota, 'commission_rate' => 15, 'is_active' => true]);
    }

    /**
     * @return array{0: RoomType, 1: list<Room>}
     */
    private function roomsOfOneType(int $count = 3): array
    {
        $type = RoomType::factory()->create();

        return [$type, Room::factory()->count($count)->create(['room_type_id' => $type->id])->all()];
    }

    private function book(Room $room, ?SalesChannel $channel, int $fromDay, int $toDay, array $overrides = []): Booking
    {
        return Booking::factory()->create($overrides + [
            'room_id' => $room->id,
            'sales_channel_id' => $channel?->id,
            'check_in_date' => now()->addDays($fromDay)->toDateString(),
            'check_out_date' => now()->addDays($toDay)->toDateString(),
        ]);
    }

    public function test_a_channel_cannot_hold_more_units_than_its_allotment_on_one_night(): void
    {
        [$type, $rooms] = $this->roomsOfOneType();
        $channel = $this->channel();
        ChannelAllotment::query()->create(['room_type_id' => $type->id, 'sales_channel_id' => $channel->id, 'allotment_count' => 2]);

        $this->book($rooms[0], $channel, 3, 6);
        $this->book($rooms[1], $channel, 4, 7);

        $this->expectException(ChannelAllotmentExceededException::class);
        $this->expectExceptionMessage('حداکثر 2 واحد');

        $this->book($rooms[2], $channel, 5, 8);
    }

    public function test_back_to_back_stays_do_not_count_together(): void
    {
        [$type, $rooms] = $this->roomsOfOneType();
        $channel = $this->channel();
        ChannelAllotment::query()->create(['room_type_id' => $type->id, 'sales_channel_id' => $channel->id, 'allotment_count' => 1]);

        $this->book($rooms[0], $channel, 3, 5);
        $second = $this->book($rooms[1], $channel, 5, 7);

        $this->assertNotNull($second->id);
    }

    public function test_other_channels_direct_sales_and_other_room_types_are_not_limited(): void
    {
        [$type, $rooms] = $this->roomsOfOneType();
        [, $otherRooms] = $this->roomsOfOneType(1);
        $limited = $this->channel();
        $free = $this->channel('کانال آزاد');
        ChannelAllotment::query()->create(['room_type_id' => $type->id, 'sales_channel_id' => $limited->id, 'allotment_count' => 1]);

        $this->book($rooms[0], $limited, 3, 5);
        $this->book($rooms[1], $free, 3, 5);
        $this->book($rooms[2], null, 3, 5);
        $this->book($otherRooms[0], $limited, 3, 5);

        $this->assertSame(4, Booking::count());
    }

    public function test_cancelled_bookings_free_their_unit(): void
    {
        [$type, $rooms] = $this->roomsOfOneType();
        $channel = $this->channel();
        ChannelAllotment::query()->create(['room_type_id' => $type->id, 'sales_channel_id' => $channel->id, 'allotment_count' => 1]);

        $first = $this->book($rooms[0], $channel, 3, 5);
        $first->cancel();

        $this->assertNotNull($this->book($rooms[1], $channel, 3, 5)->id);
    }

    public function test_an_allotment_of_zero_closes_the_channel_for_that_room_type(): void
    {
        [$type, $rooms] = $this->roomsOfOneType(1);
        $channel = $this->channel();
        ChannelAllotment::query()->create(['room_type_id' => $type->id, 'sales_channel_id' => $channel->id, 'allotment_count' => 0]);

        $this->expectException(ChannelAllotmentExceededException::class);
        $this->expectExceptionMessage('سهمیه صفر');

        $this->book($rooms[0], $channel, 3, 5);
    }

    public function test_moving_a_booking_onto_a_full_night_is_refused_but_check_in_is_not(): void
    {
        [$type, $rooms] = $this->roomsOfOneType();
        $channel = $this->channel();
        ChannelAllotment::query()->create(['room_type_id' => $type->id, 'sales_channel_id' => $channel->id, 'allotment_count' => 1]);

        $this->book($rooms[0], $channel, 3, 5);
        $later = $this->book($rooms[1], $channel, 10, 12);

        try {
            $later->update(['check_in_date' => now()->addDays(4)->toDateString(), 'check_out_date' => now()->addDays(6)->toDateString()]);
            $this->fail('The move onto a full night should have been refused.');
        } catch (RoomNotAvailableException $exception) {
            $this->assertInstanceOf(ChannelAllotmentExceededException::class, $exception);
        }

        // The allotment is lowered after the guest booked: checking in still works.
        ChannelAllotment::query()->update(['allotment_count' => 0]);
        $later->fresh()->checkIn();
        $this->assertSame(BookingStatus::CheckedIn, $later->fresh()->status);
    }

    public function test_reviving_a_cancelled_booking_is_checked_against_the_allotment(): void
    {
        [$type, $rooms] = $this->roomsOfOneType();
        $channel = $this->channel();
        ChannelAllotment::query()->create(['room_type_id' => $type->id, 'sales_channel_id' => $channel->id, 'allotment_count' => 1]);

        $first = $this->book($rooms[0], $channel, 3, 5);
        $first->cancel();
        $this->book($rooms[1], $channel, 3, 5);

        $this->expectException(ChannelAllotmentExceededException::class);

        $first->fresh()->update(['status' => BookingStatus::Confirmed]);
    }

    public function test_the_external_api_answers_409_when_the_channel_is_full(): void
    {
        [$type, $rooms] = $this->roomsOfOneType();
        $channel = $this->channel();
        ChannelAllotment::query()->create(['room_type_id' => $type->id, 'sales_channel_id' => $channel->id, 'allotment_count' => 1]);
        $this->book($rooms[0], $channel, 3, 5);

        $client = ExternalApiClient::factory()->create();

        $this->postJson('/api/v1/external/bookings', [
            'room_id' => $rooms[1]->id,
            'sales_channel_id' => $channel->id,
            'check_in_date' => now()->addDays(3)->toDateString(),
            'check_out_date' => now()->addDays(5)->toDateString(),
            'guest' => ['name' => 'مریم احمدی', 'mobile' => '09121112233'],
        ], $this->apiHeaders($client) + ['Idempotency-Key' => 'allot-1'])
            ->assertStatus(409)
            ->assertJsonPath('error.code', 'CONFLICT');
    }

    public function test_availability_hides_room_types_a_channel_has_used_up(): void
    {
        [$type, $rooms] = $this->roomsOfOneType();
        $channel = $this->channel();
        ChannelAllotment::query()->create(['room_type_id' => $type->id, 'sales_channel_id' => $channel->id, 'allotment_count' => 1]);
        $this->book($rooms[0], $channel, 3, 5);

        $client = ExternalApiClient::factory()->create();
        $query = ['check_in' => now()->addDays(3)->toDateString(), 'check_out' => now()->addDays(5)->toDateString()];

        $this->getJson('/api/v1/external/rooms/availability?'.http_build_query($query), $this->apiHeaders($client))
            ->assertOk()->assertJsonCount(2, 'data');

        $this->getJson('/api/v1/external/rooms/availability?'.http_build_query($query + ['sales_channel_id' => $channel->id]), $this->apiHeaders($client))
            ->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_today_page_lists_arrivals_and_departures_and_acts_on_them(): void
    {
        $arriving = $this->book(Room::factory()->create(), null, 0, 2, ['status' => BookingStatus::Confirmed, 'guest_id' => Guest::factory()->create(['name' => 'علی واردشونده'])->id]);
        $leaving = $this->book(Room::factory()->create(), null, -2, 0, ['status' => BookingStatus::CheckedIn, 'guest_id' => Guest::factory()->create(['name' => 'رضا خروجی'])->id]);
        $this->book(Room::factory()->create(), null, 3, 5, ['guest_id' => Guest::factory()->create(['name' => 'فردایی'])->id]);

        $page = Livewire::actingAs($this->admin())
            ->test(TodayArrivals::class)
            ->assertSee('علی واردشونده')
            ->assertSee('رضا خروجی')
            ->assertDontSee('فردایی');

        $page->call('checkIn', $arriving->id);
        $this->assertSame(BookingStatus::CheckedIn, $arriving->fresh()->status);

        $page->call('checkOut', $leaving->id);
        $this->assertSame(BookingStatus::CheckedOut, $leaving->fresh()->status);
    }

    public function test_today_page_is_forbidden_without_permission(): void
    {
        $user = User::factory()->create();
        $user->assignRole(Role::create(['name' => 'بدون دسترسی ورود امروز']));

        Livewire::actingAs($user)->test(TodayArrivals::class)->assertForbidden();
    }

    /**
     * @return array<string, string>
     */
    private function apiHeaders(ExternalApiClient $client): array
    {
        return ['X-API-Key' => $client->api_key, 'X-API-Secret' => 'test-secret'];
    }
}
