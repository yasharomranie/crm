<?php

namespace Tests\Feature\TripPlanner;

use App\Models\Attraction;
use App\Models\Event;
use App\Models\Guest;
use App\Models\TripPlan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerAppTripPlanEditTest extends TestCase
{
    use RefreshDatabase;

    private function place(array $overrides = []): Attraction
    {
        return Attraction::factory()->create([
            'opens_at' => null, 'closes_at' => null, 'avg_visit_minutes' => 60,
            'lat' => 34.80, 'lng' => 48.50, ...$overrides,
        ]);
    }

    /**
     * @param  list<Attraction>  $places
     */
    private function planWith(Guest $guest, array $places, int $days = 1): TripPlan
    {
        $plan = $guest->tripPlans()->create(['days' => $days, 'pace' => 'balanced', 'starts_on' => '2026-11-04', 'has_car' => true]);

        foreach ($places as $index => $place) {
            $start = 9 + $index * 2;
            $plan->items()->create([
                'attraction_id' => $place->id, 'day_number' => 1,
                'start_time' => sprintf('%02d:00', $start), 'end_time' => sprintf('%02d:00', $start + 1),
                'sort_order' => $index,
            ]);
        }

        return $plan;
    }

    public function test_a_stop_can_be_removed_from_the_guests_own_plan(): void
    {
        $guest = Guest::factory()->create();
        [$a, $b] = [$this->place(), $this->place()];
        $plan = $this->planWith($guest, [$a, $b]);

        $this->actingAs($guest, 'customer')
            ->delete(route('customer-app.trip-planner.items.destroy', [$plan, $plan->items()->first()]))
            ->assertSessionHasNoErrors();

        $this->assertSame([$b->id], $plan->items()->pluck('attraction_id')->all());
    }

    public function test_another_guests_plan_cannot_be_edited_or_viewed(): void
    {
        $owner = Guest::factory()->create();
        $intruder = Guest::factory()->create();
        $plan = $this->planWith($owner, [$this->place()]);
        $item = $plan->items()->first();

        $this->actingAs($intruder, 'customer')->delete(route('customer-app.trip-planner.items.destroy', [$plan, $item]))->assertNotFound();
        $this->actingAs($intruder, 'customer')->post(route('customer-app.trip-planner.days.regenerate', [$plan, 1]))->assertNotFound();
        $this->actingAs($intruder, 'customer')->post(route('customer-app.trip-planner.share', $plan))->assertNotFound();
        $this->actingAs($intruder, 'customer')->get(route('customer-app.trip-planner.calendar', $plan))->assertNotFound();

        $this->assertSame(1, $plan->items()->count());
    }

    public function test_an_item_of_another_plan_cannot_be_removed_through_this_plan(): void
    {
        $guest = Guest::factory()->create();
        $mine = $this->planWith($guest, [$this->place()]);
        $other = $this->planWith($guest, [$this->place()]);

        $this->actingAs($guest, 'customer')
            ->delete(route('customer-app.trip-planner.items.destroy', [$mine, $other->items()->first()]))
            ->assertNotFound();
    }

    public function test_a_stop_can_be_added_after_the_previous_one_with_travel_time(): void
    {
        $guest = Guest::factory()->create();
        $first = $this->place();
        $new = $this->place(['lat' => 34.75, 'lng' => 48.45]);
        $plan = $this->planWith($guest, [$first]);

        $this->actingAs($guest, 'customer')
            ->post(route('customer-app.trip-planner.items.store', $plan), ['attraction_id' => $new->id, 'day_number' => 1])
            ->assertSessionHasNoErrors();

        $added = $plan->items()->where('attraction_id', $new->id)->sole();
        $this->assertGreaterThanOrEqual(15, $added->travel_minutes);
        $this->assertGreaterThan('10:00', substr($added->start_time, 0, 5));
    }

    public function test_adding_a_place_already_in_the_plan_or_closed_that_day_is_refused(): void
    {
        $guest = Guest::factory()->create();
        $first = $this->place();
        $closedOnWednesday = $this->place(['closed_weekdays' => [3]]); // 2026-11-04 is a Wednesday
        $plan = $this->planWith($guest, [$first]);

        $this->actingAs($guest, 'customer')
            ->post(route('customer-app.trip-planner.items.store', $plan), ['attraction_id' => $first->id, 'day_number' => 1])
            ->assertSessionHasErrors('edit');

        $this->actingAs($guest, 'customer')
            ->post(route('customer-app.trip-planner.items.store', $plan), ['attraction_id' => $closedOnWednesday->id, 'day_number' => 1])
            ->assertSessionHasErrors('edit');

        $this->assertSame(1, $plan->items()->count());
    }

    public function test_moving_a_stop_down_swaps_the_order_and_keeps_times_sequential(): void
    {
        $guest = Guest::factory()->create();
        [$a, $b] = [$this->place(), $this->place()];
        $plan = $this->planWith($guest, [$a, $b]);

        $this->actingAs($guest, 'customer')
            ->post(route('customer-app.trip-planner.items.move', [$plan, $plan->items()->orderBy('sort_order')->first()]), ['direction' => 'down'])
            ->assertSessionHasNoErrors();

        $items = $plan->items()->orderBy('sort_order')->get();
        $this->assertSame([$b->id, $a->id], $items->pluck('attraction_id')->all());
        $this->assertLessThanOrEqual($items[1]->start_time, $items[0]->start_time);
        $this->assertLessThan($items[1]->start_time, $items[0]->end_time);
    }

    public function test_a_day_can_be_regenerated_with_places_not_used_elsewhere(): void
    {
        $guest = Guest::factory()->create();
        $current = $this->place();
        $spare = $this->place();
        $plan = $this->planWith($guest, [$current]);

        $this->actingAs($guest, 'customer')
            ->post(route('customer-app.trip-planner.days.regenerate', [$plan, 1]))
            ->assertSessionHasNoErrors();

        $this->assertContains($spare->id, $plan->items()->pluck('attraction_id')->all());
    }

    public function test_the_plan_can_be_rated(): void
    {
        $guest = Guest::factory()->create();
        $plan = $this->planWith($guest, [$this->place()]);

        $this->actingAs($guest, 'customer')
            ->post(route('customer-app.trip-planner.rate', $plan), ['rating' => 4, 'feedback' => 'عالی بود'])
            ->assertSessionHasNoErrors();

        $this->assertSame(4, $plan->fresh()->rating);
        $this->assertSame('عالی بود', $plan->fresh()->feedback);

        $this->actingAs($guest, 'customer')
            ->post(route('customer-app.trip-planner.rate', $plan), ['rating' => 9])
            ->assertSessionHasErrors('rating');
    }

    public function test_the_share_link_shows_a_read_only_page_and_can_be_turned_off(): void
    {
        $guest = Guest::factory()->create();
        $place = $this->place(['name' => 'غار علی‌صدر']);
        $plan = $this->planWith($guest, [$place]);

        $this->actingAs($guest, 'customer')->post(route('customer-app.trip-planner.share', $plan));
        $token = $plan->fresh()->share_token;
        $this->assertNotNull($token);

        $this->app['auth']->forgetGuards();
        $this->get(route('trip-plan.public', $token))
            ->assertOk()
            ->assertSee('غار علی‌صدر')
            ->assertDontSee('حذف')
            ->assertDontSee('ساخت لینک اشتراک');

        $this->actingAs($guest, 'customer')->delete(route('customer-app.trip-planner.unshare', $plan));
        $this->app['auth']->forgetGuards();

        $this->get(route('trip-plan.public', $token))->assertNotFound();
    }

    public function test_the_calendar_file_has_one_event_per_stop_in_tehran_time(): void
    {
        $guest = Guest::factory()->create();
        $plan = $this->planWith($guest, [$this->place(['name' => 'بوعلی']), $this->place(['name' => 'گنج‌نامه'])]);

        $response = $this->actingAs($guest, 'customer')->get(route('customer-app.trip-planner.calendar', $plan));

        $response->assertOk();
        $this->assertStringContainsString('text/calendar', $response->headers->get('Content-Type'));
        $body = $response->getContent();
        $this->assertSame(2, substr_count($body, 'BEGIN:VEVENT'));
        $this->assertStringContainsString('DTSTART;TZID=Asia/Tehran:20261104T090000', $body);
        $this->assertStringContainsString('SUMMARY:بوعلی', $body);
    }

    public function test_events_of_the_trip_days_are_listed_on_the_plan(): void
    {
        $guest = Guest::factory()->create();
        $plan = $this->planWith($guest, [$this->place()]);
        Event::create(['title' => 'جشنواره گل', 'starts_at' => '2026-11-04 17:00:00']);
        Event::create(['title' => 'رویداد دور', 'starts_at' => '2027-03-01 17:00:00']);

        $this->actingAs($guest, 'customer')->get(route('customer-app.trip-planner.show', $plan))
            ->assertSee('جشنواره گل')
            ->assertDontSee('رویداد دور');
    }

    public function test_the_plan_page_offers_ticket_and_booking_links_with_the_trip_dates(): void
    {
        $guest = Guest::factory()->create();
        $plan = $this->planWith($guest, [$this->place()]);

        $this->actingAs($guest, 'customer')->get(route('customer-app.trip-planner.show', $plan))
            ->assertSee(route('customer-app.tickets'))
            ->assertSee('check_in_date=2026-11-04', false);
    }
}
