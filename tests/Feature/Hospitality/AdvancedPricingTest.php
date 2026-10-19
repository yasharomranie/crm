<?php

namespace Tests\Feature\Hospitality;

use App\Domain\Hospitality\StayPricing;
use App\Enums\PriceAdjustmentType;
use App\Exceptions\InvalidPromoCodeException;
use App\Exceptions\MinimumStayException;
use App\Filament\Resources\BookingPromoCodes\Pages\ManageBookingPromoCodes;
use App\Filament\Resources\StayDiscounts\Pages\ManageStayDiscounts;
use App\Models\Booking;
use App\Models\BookingPromoCode;
use App\Models\ExternalApiClient;
use App\Models\PriceRule;
use App\Models\Room;
use App\Models\RoomType;
use App\Models\StayDiscount;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AdvancedPricingTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $admin = User::factory()->create();
        $admin->assignRole(Role::firstOrCreate(['name' => 'admin']));

        return $admin;
    }

    private function roomType(array $overrides = []): RoomType
    {
        return RoomType::factory()->create($overrides + ['base_price' => 1_000_000, 'capacity' => 4, 'min_nights' => 1]);
    }

    private function room(RoomType $type): Room
    {
        return Room::factory()->create(['room_type_id' => $type->id]);
    }

    private function book(Room $room, int $nights = 2, array $overrides = []): Booking
    {
        return Booking::factory()->create($overrides + [
            'room_id' => $room->id,
            'check_in_date' => now()->addDays(10)->toDateString(),
            'check_out_date' => now()->addDays(10 + $nights)->toDateString(),
            'adults_count' => 1,
            'children_count' => 0,
        ]);
    }

    private function quote(RoomType $type, string $in, string $out, int $adults = 1, int $children = 0, ?BookingPromoCode $promo = null): array
    {
        return app(StayPricing::class)->quote($type->fresh(), Carbon::parse($in), Carbon::parse($out), $adults, $children, $promo);
    }

    private function promo(array $overrides = []): BookingPromoCode
    {
        return BookingPromoCode::query()->create($overrides + ['code' => 'NOWRUZ', 'discount_type' => PriceAdjustmentType::Percentage, 'discount_value' => 10, 'is_active' => true]);
    }

    public function test_a_weekday_rule_prices_only_the_chosen_days(): void
    {
        $type = $this->roomType();
        // Thursday (4) and Friday (5) nights cost 50% more.
        PriceRule::query()->create([
            'room_type_id' => $type->id, 'name' => 'آخر هفته', 'start_date' => '2030-01-01', 'end_date' => '2030-12-31',
            'adjustment_type' => PriceAdjustmentType::Percentage, 'adjustment_value' => 50, 'weekdays' => [4, 5], 'is_active' => true,
        ]);

        // Wednesday 2030-01-02 -> Friday 2030-01-04: nights Wed (normal) and Thu (+50%).
        $quote = $this->quote($type, '2030-01-02', '2030-01-04');

        $this->assertSame([1_000_000, 1_500_000], array_column($quote['night_rates'], 'rate'));
        $this->assertSame(2_500_000, $quote['total']);
    }

    public function test_a_rule_without_weekdays_applies_every_day(): void
    {
        $type = $this->roomType();
        PriceRule::query()->create([
            'room_type_id' => $type->id, 'name' => 'نوروز', 'start_date' => '2030-01-01', 'end_date' => '2030-01-31',
            'adjustment_type' => PriceAdjustmentType::Fixed, 'adjustment_value' => 200_000, 'is_active' => true,
        ]);

        $this->assertSame(2_400_000, $this->quote($type, '2030-01-10', '2030-01-12')['total']);
    }

    public function test_extra_adults_and_children_pay_per_night(): void
    {
        $type = $this->roomType(['base_occupancy' => 2, 'extra_adult_fee' => 300_000, 'extra_child_fee' => 100_000]);

        $quote = $this->quote($type, '2030-03-01', '2030-03-04', 4, 1);

        // 3 nights x (2 extra adults x 300k + 1 child x 100k) = 2.1M on top of 3M.
        $this->assertSame(2_100_000, $quote['extra_guests_total']);
        $this->assertSame(5_100_000, $quote['total']);
    }

    public function test_without_a_base_occupancy_adults_are_never_charged_extra(): void
    {
        $type = $this->roomType(['extra_adult_fee' => 300_000]);

        $this->assertSame(0, $this->quote($type, '2030-03-01', '2030-03-03', 6)['extra_guests_total']);
    }

    public function test_the_best_long_stay_tier_applies_and_tiers_do_not_add_up(): void
    {
        $type = $this->roomType();
        $other = $this->roomType();
        StayDiscount::query()->create(['room_type_id' => null, 'min_nights' => 3, 'percent' => 5]);
        StayDiscount::query()->create(['room_type_id' => $type->id, 'min_nights' => 7, 'percent' => 15]);
        StayDiscount::query()->create(['room_type_id' => $type->id, 'min_nights' => 30, 'percent' => 40, 'is_active' => false]);

        $this->assertSame(0, $this->quote($type, '2030-03-01', '2030-03-03')['stay_discount']);
        $this->assertSame(150_000, $this->quote($type, '2030-03-01', '2030-03-04')['stay_discount']);
        $this->assertSame(1_050_000, $this->quote($type, '2030-03-01', '2030-03-08')['stay_discount']);
        $this->assertSame(350_000, $this->quote($other, '2030-03-01', '2030-03-08')['stay_discount']);
    }

    public function test_a_percentage_code_applies_after_the_long_stay_discount(): void
    {
        $type = $this->roomType();
        StayDiscount::query()->create(['min_nights' => 2, 'percent' => 10]);

        $quote = $this->quote($type, '2030-03-01', '2030-03-03', promo: $this->promo(['discount_value' => 10]));

        // 2M - 10% (200k) = 1.8M, minus 10% of that (180k).
        $this->assertSame(200_000, $quote['stay_discount']);
        $this->assertSame(180_000, $quote['promo_discount']);
        $this->assertSame(1_620_000, $quote['total']);
    }

    public function test_a_fixed_code_never_exceeds_the_room_total(): void
    {
        $type = $this->roomType();

        $quote = $this->quote($type, '2030-03-01', '2030-03-02', promo: $this->promo(['discount_type' => PriceAdjustmentType::Fixed, 'discount_value' => 5_000_000]));

        $this->assertSame(1_000_000, $quote['promo_discount']);
        $this->assertSame(0, $quote['total']);
    }

    public function test_promo_codes_are_checked_for_validity(): void
    {
        $this->promo(['code' => 'OFF', 'is_active' => false]);
        $this->promo(['code' => 'EXPIRED', 'valid_until' => now()->subDay()->toDateString()]);
        $this->promo(['code' => 'FUTURE', 'valid_from' => now()->addDay()->toDateString()]);
        $this->promo(['code' => 'USEDUP', 'usage_limit' => 1, 'used_count' => 1]);
        $this->promo(['code' => 'LONGONLY', 'min_nights' => 5]);
        $this->promo(['code' => 'GOOD']);

        foreach (['MISSING', 'OFF', 'EXPIRED', 'FUTURE', 'USEDUP', 'LONGONLY'] as $code) {
            try {
                BookingPromoCode::resolve($code, 2);
                $this->fail("{$code} should have been refused.");
            } catch (InvalidPromoCodeException) {
                $this->addToAssertionCount(1);
            }
        }

        $this->assertSame('GOOD', BookingPromoCode::resolve(' good ', 2)->code);
    }

    public function test_a_booking_folio_gets_the_extra_guest_and_discount_lines(): void
    {
        $type = $this->roomType(['base_occupancy' => 1, 'extra_adult_fee' => 200_000]);
        StayDiscount::query()->create(['min_nights' => 2, 'percent' => 10]);
        $promo = $this->promo(['discount_value' => 10]);

        $booking = $this->book($this->room($type), 2, ['adults_count' => 2, 'promo_code_id' => $promo->id])->fresh();

        // 2M + 400k extra - 200k (long stay) - 180k (code).
        $this->assertSame(2_020_000, $booking->totalAmount());
        $this->assertSame(4, $booking->charges()->count());
        $this->assertSame(1, $promo->fresh()->used_count);
    }

    public function test_a_plain_stay_keeps_a_single_folio_line(): void
    {
        $booking = $this->book($this->room($this->roomType()), 3)->fresh();

        $this->assertSame(1, $booking->charges()->count());
        $this->assertSame(3_000_000, $booking->totalAmount());
    }

    public function test_a_code_that_ran_out_is_refused_at_booking_time(): void
    {
        $promo = $this->promo(['usage_limit' => 1]);
        $room = $this->room($this->roomType());
        $this->book($room, 2, ['promo_code_id' => $promo->id]);

        $this->expectException(InvalidPromoCodeException::class);

        $this->book($this->room($room->roomType), 2, ['promo_code_id' => $promo->id]);
    }

    public function test_a_stay_shorter_than_the_room_types_minimum_is_refused(): void
    {
        $room = $this->room($this->roomType(['min_nights' => 3]));

        $this->expectException(MinimumStayException::class);
        $this->expectExceptionMessage('3 شب');

        $this->book($room, 2);
    }

    public function test_a_price_rule_can_demand_a_longer_stay_only_on_its_dates(): void
    {
        $type = $this->roomType();
        PriceRule::query()->create([
            'room_type_id' => $type->id, 'name' => 'نوروز', 'start_date' => now()->addDays(9)->toDateString(), 'end_date' => now()->addDays(20)->toDateString(),
            'adjustment_type' => PriceAdjustmentType::Percentage, 'adjustment_value' => 0, 'min_nights' => 4, 'is_active' => true,
        ]);
        $room = $this->room($type);

        // Outside the rule's dates a short stay is fine.
        $this->assertNotNull($this->book($room, 1, ['check_in_date' => now()->addDays(40)->toDateString(), 'check_out_date' => now()->addDays(41)->toDateString()])->id);

        $this->expectException(MinimumStayException::class);
        $this->expectExceptionMessage('4 شب');

        $this->book($room, 2);
    }

    public function test_moving_a_booking_to_a_too_short_stay_is_refused(): void
    {
        $room = $this->room($this->roomType(['min_nights' => 2]));
        $booking = $this->book($room, 3);

        $this->expectException(MinimumStayException::class);

        $booking->update(['check_out_date' => now()->addDays(11)->toDateString()]);
    }

    public function test_the_external_api_quotes_a_stay_and_books_it_with_a_code(): void
    {
        $type = $this->roomType();
        $room = $this->room($type);
        $this->promo(['discount_value' => 10]);
        $client = ExternalApiClient::factory()->create();
        $headers = ['X-API-Key' => $client->api_key, 'X-API-Secret' => 'test-secret'];
        $in = now()->addDays(5)->toDateString();
        $out = now()->addDays(7)->toDateString();

        $this->getJson('/api/v1/external/rooms/availability?'.http_build_query(['check_in' => $in, 'check_out' => $out, 'promo_code' => 'NOWRUZ']), $headers)
            ->assertOk()
            ->assertJsonPath('data.0.price_for_stay', 2_000_000)
            ->assertJsonPath('data.0.quote.promo_discount', 200_000)
            ->assertJsonPath('data.0.quote.total', 1_800_000);

        $this->postJson('/api/v1/external/bookings', [
            'room_id' => $room->id, 'check_in_date' => $in, 'check_out_date' => $out, 'promo_code' => 'nowruz',
            'guest' => ['name' => 'مریم احمدی', 'mobile' => '09121112233'],
        ], [...$headers, 'Idempotency-Key' => 'promo-1'])
            ->assertCreated()
            ->assertJsonPath('total_amount', 1_800_000)
            ->assertJsonPath('promo_code', 'NOWRUZ');

        $this->postJson('/api/v1/external/bookings', [
            'room_id' => $room->id, 'check_in_date' => now()->addDays(20)->toDateString(), 'check_out_date' => now()->addDays(22)->toDateString(), 'promo_code' => 'NOPE',
            'guest' => ['name' => 'علی', 'mobile' => '09120001122'],
        ], [...$headers, 'Idempotency-Key' => 'promo-2'])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'INVALID_PROMO_CODE');
    }

    public function test_the_availability_list_hides_rooms_whose_minimum_stay_is_not_met(): void
    {
        $this->room($this->roomType(['min_nights' => 3]));
        $client = ExternalApiClient::factory()->create();
        $headers = ['X-API-Key' => $client->api_key, 'X-API-Secret' => 'test-secret'];

        $this->getJson('/api/v1/external/rooms/availability?'.http_build_query(['check_in' => now()->addDays(5)->toDateString(), 'check_out' => now()->addDays(7)->toDateString()]), $headers)
            ->assertOk()->assertJsonCount(0, 'data');

        $this->getJson('/api/v1/external/rooms/availability?'.http_build_query(['check_in' => now()->addDays(5)->toDateString(), 'check_out' => now()->addDays(8)->toDateString()]), $headers)
            ->assertOk()->assertJsonCount(1, 'data');
    }

    public function test_the_admin_pages_for_discounts_and_codes_open(): void
    {
        StayDiscount::query()->create(['min_nights' => 7, 'percent' => 12]);
        $this->promo();

        Livewire::actingAs($this->admin())->test(ManageStayDiscounts::class)->assertSuccessful();
        Livewire::actingAs($this->admin())->test(ManageBookingPromoCodes::class)->assertSuccessful()->assertSee('NOWRUZ');
    }
}
