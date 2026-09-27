<?php

namespace Tests\Feature\Hospitality;

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\ExternalApiClient;
use App\Models\Guest;
use App\Models\Room;
use App\Models\RoomType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExternalBookingApiTest extends TestCase
{
    use RefreshDatabase;

    private function headers(ExternalApiClient $client, string $secret = 'test-secret'): array
    {
        return ['X-API-Key' => $client->api_key, 'X-API-Secret' => $secret];
    }

    public function test_availability_lists_only_rooms_free_for_the_requested_range(): void
    {
        $roomType = RoomType::factory()->create();
        $freeRoom = Room::factory()->create(['room_type_id' => $roomType->id]);
        $bookedRoom = Room::factory()->create(['room_type_id' => $roomType->id]);
        $client = ExternalApiClient::factory()->create();

        Booking::factory()->create([
            'room_id' => $bookedRoom->id,
            'check_in_date' => '2026-11-01',
            'check_out_date' => '2026-11-05',
        ]);

        $response = $this->getJson('/api/v1/external/rooms/availability?'.http_build_query([
            'check_in' => '2026-11-02',
            'check_out' => '2026-11-04',
        ]), $this->headers($client));

        $response->assertOk();
        $roomIds = collect($response->json('data'))->pluck('room_id');
        $this->assertTrue($roomIds->contains($freeRoom->id));
        $this->assertFalse($roomIds->contains($bookedRoom->id));
    }

    public function test_a_booking_can_be_created_for_a_new_guest(): void
    {
        $room = Room::factory()->create();
        $client = ExternalApiClient::factory()->create();

        $response = $this->postJson('/api/v1/external/bookings', [
            'room_id' => $room->id,
            'check_in_date' => '2026-11-10',
            'check_out_date' => '2026-11-12',
            'guest' => ['name' => 'علی رضایی', 'mobile' => '09121234567'],
        ], [...$this->headers($client), 'Idempotency-Key' => 'bk-1']);

        $response->assertStatus(201);
        $this->assertSame(1, Booking::count());
        $this->assertSame(1, Guest::where('mobile', '09121234567')->count());
        $this->assertSame($client->id, Booking::first()->external_api_client_id);
    }

    public function test_booking_the_same_room_and_overlapping_dates_twice_is_a_conflict(): void
    {
        $room = Room::factory()->create();
        $client = ExternalApiClient::factory()->create();
        $payload = [
            'room_id' => $room->id,
            'check_in_date' => '2026-12-01',
            'check_out_date' => '2026-12-03',
            'guest' => ['name' => 'اول', 'mobile' => '09120000001'],
        ];

        $this->postJson('/api/v1/external/bookings', $payload, [...$this->headers($client), 'Idempotency-Key' => 'bk-a'])
            ->assertStatus(201);

        $payload['guest'] = ['name' => 'دوم', 'mobile' => '09120000002'];
        $response = $this->postJson('/api/v1/external/bookings', $payload, [...$this->headers($client), 'Idempotency-Key' => 'bk-b']);

        $response->assertStatus(409);
        $this->assertSame(1, Booking::count());
    }

    public function test_a_client_can_read_back_its_own_booking_by_reference_code(): void
    {
        $room = Room::factory()->create();
        $client = ExternalApiClient::factory()->create();

        $create = $this->postJson('/api/v1/external/bookings', [
            'room_id' => $room->id,
            'check_in_date' => '2026-11-20',
            'check_out_date' => '2026-11-22',
            'guest' => ['name' => 'مریم احمدی', 'mobile' => '09123334455'],
        ], [...$this->headers($client), 'Idempotency-Key' => 'bk-2']);

        $referenceCode = $create->json('reference_code');

        $this->getJson("/api/v1/external/bookings/{$referenceCode}", $this->headers($client))
            ->assertOk()
            ->assertJsonPath('reference_code', $referenceCode);
    }

    public function test_a_client_cannot_read_a_booking_created_by_a_different_client(): void
    {
        $room = Room::factory()->create();
        $owner = ExternalApiClient::factory()->create();
        $stranger = ExternalApiClient::factory()->create();

        $create = $this->postJson('/api/v1/external/bookings', [
            'room_id' => $room->id,
            'check_in_date' => '2026-11-25',
            'check_out_date' => '2026-11-27',
            'guest' => ['name' => 'سارا', 'mobile' => '09121112233'],
        ], [...$this->headers($owner), 'Idempotency-Key' => 'bk-3']);

        $referenceCode = $create->json('reference_code');

        $this->getJson("/api/v1/external/bookings/{$referenceCode}", $this->headers($stranger))
            ->assertStatus(404);
    }

    public function test_a_booking_made_through_the_admin_panel_is_not_readable_through_the_api(): void
    {
        $booking = Booking::factory()->create(['status' => BookingStatus::Confirmed]);
        $client = ExternalApiClient::factory()->create();

        $this->getJson("/api/v1/external/bookings/{$booking->reference_code}", $this->headers($client))
            ->assertStatus(404);
    }

    public function test_a_client_without_booking_create_permission_is_forbidden(): void
    {
        $room = Room::factory()->create();
        $client = ExternalApiClient::factory()->create(['allowed_operations' => ['room.read']]);

        $response = $this->postJson('/api/v1/external/bookings', [
            'room_id' => $room->id,
            'check_in_date' => '2026-11-10',
            'check_out_date' => '2026-11-12',
            'guest' => ['name' => 'علی رضایی', 'mobile' => '09121234567'],
        ], [...$this->headers($client), 'Idempotency-Key' => 'bk-forbidden']);

        $response->assertStatus(403)->assertJsonPath('error.code', 'FORBIDDEN');
        $this->assertSame(0, Booking::count());
    }

    public function test_a_client_without_booking_read_permission_is_forbidden(): void
    {
        $booking = Booking::factory()->create();
        $client = ExternalApiClient::factory()->create(['allowed_operations' => ['booking.create']]);

        $this->getJson("/api/v1/external/bookings/{$booking->reference_code}", $this->headers($client))
            ->assertStatus(403)
            ->assertJsonPath('error.code', 'FORBIDDEN');
    }
}
