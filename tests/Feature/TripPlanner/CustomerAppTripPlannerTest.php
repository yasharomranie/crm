<?php

namespace Tests\Feature\TripPlanner;

use App\Domain\TripPlanner\TripPlannerService;
use App\Enums\AttractionCategory;
use App\Jobs\BuildTripPlanJob;
use App\Models\AiProvider;
use App\Models\Attraction;
use App\Models\Guest;
use App\Models\TripPlannerSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class CustomerAppTripPlannerTest extends TestCase
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

    public function test_a_guest_cannot_view_the_trip_planner_without_logging_in(): void
    {
        $this->get(route('customer-app.trip-planner'))
            ->assertRedirect(route('customer-app.auth.login'));
    }

    public function test_the_index_page_lists_the_guests_own_past_plans(): void
    {
        $guest = Guest::factory()->create();
        $otherGuest = Guest::factory()->create();

        $guest->tripPlans()->create(['days' => 2, 'pace' => 'balanced']);
        $otherGuest->tripPlans()->create(['days' => 3, 'pace' => 'balanced']);

        $response = $this->actingAs($guest, 'customer')->get(route('customer-app.trip-planner'));

        $response->assertOk();
        $response->assertSee('برنامه 2 روزه');
        $response->assertDontSee('برنامه 3 روزه');
    }

    public function test_store_shows_a_friendly_error_when_no_ai_provider_is_configured(): void
    {
        $guest = Guest::factory()->create();

        $response = $this->actingAs($guest, 'customer')->post(route('customer-app.trip-planner.store'), [
            'days' => 2,
            'pace' => 'balanced',
        ]);

        $response->assertSessionHasErrors('days');
        $this->assertSame(0, $guest->tripPlans()->count());
    }

    public function test_store_generates_a_plan_and_redirects_to_its_show_page(): void
    {
        $this->activeProvider();
        $attraction = Attraction::factory()->create(['category' => AttractionCategory::Nature]);

        $this->fakeChatResponse([
            'overview' => 'یک برنامه عالی',
            'days' => [
                ['day_number' => 1, 'items' => [
                    ['attraction_id' => $attraction->id, 'start_time' => '09:00', 'end_time' => '10:00', 'note' => 'یادداشت'],
                ]],
            ],
        ]);

        $guest = Guest::factory()->create();

        $response = $this->actingAs($guest, 'customer')->post(route('customer-app.trip-planner.store'), [
            'days' => 1,
            'pace' => 'balanced',
            'interests' => ['nature'],
        ]);

        $plan = $guest->tripPlans()->first();
        $this->assertNotNull($plan);
        $response->assertRedirect(route('customer-app.trip-planner.show', $plan));
    }

    public function test_a_guest_can_view_their_own_generated_plan(): void
    {
        $this->activeProvider();
        $attraction = Attraction::factory()->create(['category' => AttractionCategory::Nature, 'name' => 'غار علی‌صدر']);

        $guest = Guest::factory()->create();
        $plan = $guest->tripPlans()->create(['days' => 1, 'pace' => 'balanced', 'overview' => 'معرفی برنامه']);
        $plan->items()->create([
            'attraction_id' => $attraction->id,
            'day_number' => 1,
            'start_time' => '09:00',
            'end_time' => '10:00',
            'note' => 'نکته بازدید',
            'sort_order' => 0,
        ]);

        $response = $this->actingAs($guest, 'customer')->get(route('customer-app.trip-planner.show', $plan));

        $response->assertOk();
        $response->assertSee('غار علی‌صدر');
        $response->assertSee('نکته بازدید');
        $response->assertSee($attraction->directionsUrl());
    }

    public function test_a_guest_cannot_view_a_plan_that_isnt_theirs(): void
    {
        $guest = Guest::factory()->create();
        $otherGuest = Guest::factory()->create();
        $plan = $otherGuest->tripPlans()->create(['days' => 1, 'pace' => 'balanced']);

        $this->actingAs($guest, 'customer')
            ->get(route('customer-app.trip-planner.show', $plan))
            ->assertNotFound();
    }

    public function test_store_queues_the_build_and_shows_a_waiting_page_until_it_is_ready(): void
    {
        Queue::fake();
        Attraction::factory()->create(['category' => AttractionCategory::Nature]);
        $guest = Guest::factory()->create();

        $response = $this->actingAs($guest, 'customer')->post(route('customer-app.trip-planner.store'), [
            'days' => 1,
            'pace' => 'balanced',
        ]);

        $plan = $guest->tripPlans()->sole();
        $this->assertSame('pending', $plan->status);
        Queue::assertPushed(BuildTripPlanJob::class, fn (BuildTripPlanJob $job) => $job->planId === $plan->id);
        $response->assertRedirect(route('customer-app.trip-planner.show', $plan));

        $this->actingAs($guest, 'customer')
            ->get(route('customer-app.trip-planner.show', $plan))
            ->assertOk()
            ->assertSee('در حال ساخت است');
    }

    public function test_repeating_a_request_while_it_is_building_does_not_queue_a_second_build(): void
    {
        Queue::fake();
        Attraction::factory()->create();
        $guest = Guest::factory()->create();
        $payload = ['days' => 1, 'pace' => 'balanced'];

        $this->actingAs($guest, 'customer')->post(route('customer-app.trip-planner.store'), $payload);
        $this->actingAs($guest, 'customer')->post(route('customer-app.trip-planner.store'), $payload);

        $this->assertSame(1, $guest->tripPlans()->count());
        Queue::assertPushed(BuildTripPlanJob::class, 1);
    }

    public function test_the_job_builds_the_pending_plan(): void
    {
        $attraction = Attraction::factory()->create(['category' => AttractionCategory::Nature]);
        $guest = Guest::factory()->create();
        $plan = app(TripPlannerService::class)->request($guest, ['days' => 1, 'pace' => 'balanced']);

        (new BuildTripPlanJob($plan->id))->handle(app(TripPlannerService::class));

        $plan->refresh();
        $this->assertTrue($plan->isReady());
        $this->assertSame($attraction->id, $plan->items()->first()->attraction_id);
    }

    public function test_a_build_that_fails_is_recorded_and_does_not_count_against_the_daily_limit(): void
    {
        $guest = Guest::factory()->create();
        $attraction = Attraction::factory()->create();
        $service = app(TripPlannerService::class);
        $plan = $service->request($guest, ['days' => 1, 'pace' => 'balanced']);
        $attraction->update(['is_active' => false]);

        (new BuildTripPlanJob($plan->id))->handle($service);

        $plan->refresh();
        $this->assertSame('failed', $plan->status);
        $this->assertNotEmpty($plan->error_message);

        $this->actingAs($guest, 'customer')
            ->get(route('customer-app.trip-planner.show', $plan))
            ->assertOk()
            ->assertSee('ساخت برنامه کامل نشد');

        TripPlannerSetting::current()->update(['plans_per_day_limit' => 1]);
        $attraction->update(['is_active' => true]);
        $this->assertTrue($service->request($guest, ['days' => 1, 'pace' => 'balanced'])->isInProgress());
    }

    public function test_a_plan_that_is_not_ready_cannot_be_edited_or_shared(): void
    {
        $guest = Guest::factory()->create();
        $plan = $guest->tripPlans()->create(['days' => 1, 'pace' => 'balanced', 'status' => 'pending']);

        $this->actingAs($guest, 'customer')
            ->post(route('customer-app.trip-planner.share', $plan))
            ->assertNotFound();
    }
}
