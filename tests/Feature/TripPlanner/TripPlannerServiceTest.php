<?php

namespace Tests\Feature\TripPlanner;

use App\Domain\TripPlanner\TripPlannerException;
use App\Domain\TripPlanner\TripPlannerService;
use App\Enums\AttractionCategory;
use App\Exceptions\AiProviderException;
use App\Models\AiProvider;
use App\Models\Attraction;
use App\Models\Guest;
use App\Models\TripPlannerSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class TripPlannerServiceTest extends TestCase
{
    use RefreshDatabase;

    private function activeProvider(): AiProvider
    {
        return AiProvider::create([
            'name' => 'Test Provider',
            'base_url' => 'https://api.test-provider.example/v1',
            'api_key' => 'test-key',
            'default_model' => 'gpt-test',
            'is_active' => true,
        ]);
    }

    private function fakeChatResponse(array $content): void
    {
        Http::fake([
            'api.test-provider.example/*' => Http::response([
                'choices' => [['message' => ['content' => json_encode($content, JSON_UNESCAPED_UNICODE)]]],
            ]),
        ]);
    }

    private function service(): TripPlannerService
    {
        return app(TripPlannerService::class);
    }

    public function test_it_throws_when_the_trip_planner_is_disabled(): void
    {
        $this->activeProvider();
        TripPlannerSetting::current()->update(['is_enabled' => false]);

        $this->expectException(TripPlannerException::class);

        $this->service()->generate(Guest::factory()->create(), [
            'days' => 1, 'starts_on' => null, 'interests' => [], 'pace' => 'balanced',
        ]);
    }

    public function test_it_throws_when_no_ai_provider_is_active(): void
    {
        $this->expectException(AiProviderException::class);

        $this->service()->generate(Guest::factory()->create(), [
            'days' => 1, 'starts_on' => null, 'interests' => [], 'pace' => 'balanced',
        ]);
    }

    public function test_it_throws_when_there_are_no_eligible_attractions(): void
    {
        $this->activeProvider();

        $this->expectException(TripPlannerException::class);

        $this->service()->generate(Guest::factory()->create(), [
            'days' => 1, 'starts_on' => null, 'interests' => [], 'pace' => 'balanced',
        ]);
    }

    public function test_it_excludes_an_attraction_temporarily_closed_during_the_trip(): void
    {
        $this->activeProvider();

        Attraction::factory()->create([
            'name' => 'جاذبه باز',
            'category' => AttractionCategory::Nature,
            'closed_from' => null,
            'closed_until' => null,
        ]);

        $closed = Attraction::factory()->create([
            'name' => 'جاذبه بسته',
            'category' => AttractionCategory::Nature,
            'closed_from' => now()->toDateString(),
            'closed_until' => now()->addDays(5)->toDateString(),
        ]);

        $this->fakeChatResponse([
            'overview' => 'یک روز آرام در همدان',
            'days' => [
                ['day_number' => 1, 'items' => [
                    ['attraction_id' => $closed->id, 'start_time' => '09:00', 'end_time' => '10:00', 'note' => 'نباید اینجا باشد'],
                ]],
            ],
        ]);

        $this->expectException(TripPlannerException::class);

        $this->service()->generate(Guest::factory()->create(), [
            'days' => 1, 'starts_on' => now()->toDateString(), 'interests' => [], 'pace' => 'balanced',
        ]);
    }

    public function test_it_generates_and_persists_a_plan_from_a_valid_ai_response(): void
    {
        $this->activeProvider();

        $first = Attraction::factory()->create(['name' => 'غار علی‌صدر', 'category' => AttractionCategory::Nature]);
        $second = Attraction::factory()->create(['name' => 'آرامگاه بوعلی‌سینا', 'category' => AttractionCategory::Historical]);

        $this->fakeChatResponse([
            'overview' => 'یک سفر دو روزه عالی در همدان',
            'days' => [
                ['day_number' => 1, 'items' => [
                    ['attraction_id' => $first->id, 'start_time' => '09:00', 'end_time' => '11:30', 'note' => 'صبح زود شروع کنید'],
                ]],
                ['day_number' => 2, 'items' => [
                    ['attraction_id' => $second->id, 'start_time' => '10:00', 'end_time' => '11:00', 'note' => 'بازدید از آرامگاه'],
                ]],
            ],
        ]);

        $guest = Guest::factory()->create();

        $plan = $this->service()->generate($guest, [
            'days' => 2, 'starts_on' => null, 'interests' => ['nature'], 'pace' => 'balanced',
        ]);

        $this->assertSame($guest->id, $plan->guest_id);
        $this->assertSame(2, $plan->days);
        $this->assertSame('یک سفر دو روزه عالی در همدان', $plan->overview);
        $this->assertSame(['nature'], $plan->interests);
        $this->assertSame(2, $plan->items()->count());

        $dayOneItem = $plan->items()->where('day_number', 1)->first();
        $this->assertSame($first->id, $dayOneItem->attraction_id);
        $this->assertSame('صبح زود شروع کنید', $dayOneItem->note);
    }

    public function test_it_ignores_ai_items_referencing_an_unknown_attraction_id(): void
    {
        $this->activeProvider();

        $known = Attraction::factory()->create(['category' => AttractionCategory::Nature]);

        $this->fakeChatResponse([
            'overview' => 'برنامه',
            'days' => [
                ['day_number' => 1, 'items' => [
                    ['attraction_id' => $known->id, 'start_time' => '09:00', 'end_time' => '10:00', 'note' => 'معتبر'],
                    ['attraction_id' => 999999, 'start_time' => '11:00', 'end_time' => '12:00', 'note' => 'نامعتبر'],
                ]],
            ],
        ]);

        $plan = $this->service()->generate(Guest::factory()->create(), [
            'days' => 1, 'starts_on' => null, 'interests' => [], 'pace' => 'balanced',
        ]);

        $this->assertSame(1, $plan->items()->count());
        $this->assertSame($known->id, $plan->items()->first()->attraction_id);
    }

    public function test_it_throws_when_the_ai_response_is_not_valid_json(): void
    {
        $this->activeProvider();
        Attraction::factory()->create(['category' => AttractionCategory::Nature]);

        Http::fake([
            'api.test-provider.example/*' => Http::response([
                'choices' => [['message' => ['content' => 'این یک متن آزاد است، نه JSON.']]],
            ]),
        ]);

        $this->expectException(TripPlannerException::class);

        $this->service()->generate(Guest::factory()->create(), [
            'days' => 1, 'starts_on' => null, 'interests' => [], 'pace' => 'balanced',
        ]);
    }
}
