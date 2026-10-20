<?php

namespace Tests\Feature\Hospitality;

use App\Domain\Hospitality\StayPricing;
use App\Enums\PriceAdjustmentType;
use App\Filament\Resources\RatePlans\Pages\ManageRatePlans;
use App\Models\Booking;
use App\Models\CancellationPolicy;
use App\Models\ExternalApiClient;
use App\Models\PriceRule;
use App\Models\RatePlan;
use App\Models\Room;
use App\Models\RoomType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class RatePlanTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $admin = User::factory()->create();
        $admin->assignRole(Role::firstOrCreate(['name' => 'admin']));

        return $admin;
    }

    private function roomType(): RoomType
    {
        return RoomType::factory()->create(['base_price' => 1_000_000, 'capacity' => 4, 'min_nights' => 1]);
    }

    private function plan(string $type, float $value, array $overrides = []): RatePlan
    {
        return RatePlan::factory()->create($overrides + ['adjustment_type' => $type, 'adjustment_value' => $value]);
    }

    private function quote(RoomType $type, ?RatePlan $plan): array
    {
        return app(StayPricing::class)->quote($type->fresh(), Carbon::parse('2030-03-01'), Carbon::parse('2030-03-03'), ratePlan: $plan);
    }

    public function test_a_percentage_plan_moves_every_night(): void
    {
        $type = $this->roomType();

        $this->assertSame(2_000_000, $this->quote($type, null)['room_total']);
        $this->assertSame(2_300_000, $this->quote($type, $this->plan('percentage', 15))['room_total']);
        $this->assertSame(1_800_000, $this->quote($type, $this->plan('percentage', -10))['room_total']);
    }

    public function test_a_fixed_plan_adds_to_every_night_and_never_goes_below_zero(): void
    {
        $type = $this->roomType();

        $this->assertSame(2_400_000, $this->quote($type, $this->plan('fixed', 200_000))['room_total']);
        $this->assertSame(0, $this->quote($type, $this->plan('fixed', -5_000_000))['room_total']);
    }

    public function test_the_plan_moves_the_rate_after_date_rules(): void
    {
        $type = $this->roomType();
        PriceRule::query()->create([
            'room_type_id' => $type->id, 'name' => 'نوروز', 'start_date' => '2030-01-01', 'end_date' => '2030-12-31',
            'adjustment_type' => PriceAdjustmentType::Percentage, 'adjustment_value' => 50, 'is_active' => true,
        ]);

        // Rule: 1.5M a night; plan -20% of that = 1.2M a night.
        $this->assertSame(2_400_000, $this->quote($type, $this->plan('percentage', -20))['room_total']);
    }

    public function test_a_booking_prices_its_folio_with_the_plan_and_names_it(): void
    {
        $type = $this->roomType();
        $plan = $this->plan('percentage', 10, ['name' => 'با صبحانه']);

        $booking = Booking::factory()->create([
            'room_id' => Room::factory()->create(['room_type_id' => $type->id])->id,
            'rate_plan_id' => $plan->id,
            'check_in_date' => now()->addDays(10)->toDateString(),
            'check_out_date' => now()->addDays(12)->toDateString(),
            'adults_count' => 1,
        ])->fresh(['charges']);

        $this->assertSame(2_200_000, $booking->totalAmount());
        $this->assertStringContainsString('(با صبحانه)', $booking->charges->first()->title);
        $this->assertSame('با صبحانه', $booking->ratePlan->name);
    }

    public function test_a_plan_can_bring_its_own_cancellation_policy_but_an_explicit_choice_wins(): void
    {
        $default = CancellationPolicy::factory()->create(['name' => 'پیش‌فرض', 'is_default' => true, 'late_penalty_percent' => 10]);
        $strict = CancellationPolicy::factory()->create(['name' => 'سخت', 'late_penalty_percent' => 100]);
        $other = CancellationPolicy::factory()->create(['name' => 'دیگر', 'late_penalty_percent' => 50]);
        $plan = $this->plan('percentage', -10, ['cancellation_policy_id' => $strict->id]);

        $withPlan = Booking::factory()->create(['rate_plan_id' => $plan->id]);
        $explicit = Booking::factory()->create(['rate_plan_id' => $plan->id, 'cancellation_policy_id' => $other->id]);
        $plain = Booking::factory()->create();

        $this->assertSame($strict->id, $withPlan->fresh()->cancellation_policy_id);
        $this->assertSame(100, $withPlan->fresh()->cancellation_terms['late_percent']);
        $this->assertSame($other->id, $explicit->fresh()->cancellation_policy_id);
        $this->assertSame($default->id, $plain->fresh()->cancellation_policy_id);
    }

    public function test_extending_a_stay_keeps_the_plans_nightly_rate(): void
    {
        $type = $this->roomType();
        $booking = Booking::factory()->create([
            'room_id' => Room::factory()->create(['room_type_id' => $type->id])->id,
            'rate_plan_id' => $this->plan('percentage', -10)->id,
            'check_in_date' => now()->addDays(10)->toDateString(),
            'check_out_date' => now()->addDays(12)->toDateString(),
            'adults_count' => 1,
        ]);

        // 900k a night under the plan, not the plain 1M.
        $this->assertSame(1_800_000, $booking->fresh()->extensionPrice(now()->addDays(14)));
    }

    public function test_the_external_api_quotes_and_books_with_a_plan(): void
    {
        $type = $this->roomType();
        $room = Room::factory()->create(['room_type_id' => $type->id]);
        $plan = $this->plan('percentage', 20, ['name' => 'با صبحانه']);
        $client = ExternalApiClient::factory()->create();
        $headers = ['X-API-Key' => $client->api_key, 'X-API-Secret' => 'test-secret'];
        $in = now()->addDays(5)->toDateString();
        $out = now()->addDays(7)->toDateString();

        $this->getJson('/api/v1/external/rooms/availability?'.http_build_query(['check_in' => $in, 'check_out' => $out, 'rate_plan_id' => $plan->id]), $headers)
            ->assertOk()
            ->assertJsonPath('data.0.quote.total', 2_400_000);

        $this->postJson('/api/v1/external/bookings', [
            'room_id' => $room->id, 'check_in_date' => $in, 'check_out_date' => $out, 'rate_plan_id' => $plan->id,
            'guest' => ['name' => 'مریم احمدی', 'mobile' => '09121112233'],
        ], [...$headers, 'Idempotency-Key' => 'plan-1'])
            ->assertCreated()
            ->assertJsonPath('total_amount', 2_400_000)
            ->assertJsonPath('rate_plan', 'با صبحانه');

        $inactive = $this->plan('percentage', 5, ['is_active' => false]);
        $this->postJson('/api/v1/external/bookings', [
            'room_id' => $room->id, 'check_in_date' => now()->addDays(20)->toDateString(), 'check_out_date' => now()->addDays(22)->toDateString(), 'rate_plan_id' => $inactive->id,
            'guest' => ['name' => 'علی', 'mobile' => '09120001122'],
        ], [...$headers, 'Idempotency-Key' => 'plan-2'])->assertStatus(422);
    }

    public function test_the_management_page_lists_plans(): void
    {
        $this->plan('percentage', 0, ['name' => 'نرخ ویژه‌ی تست']);

        Livewire::actingAs($this->admin())->test(ManageRatePlans::class)->assertSuccessful()->assertSee('نرخ ویژه‌ی تست');
    }
}
