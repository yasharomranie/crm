<?php

namespace Tests\Feature\TripPlanner;

use App\Domain\TripPlanner\RuleBasedTripPlanner;
use App\Domain\TripPlanner\TripPlanNormalizer;
use App\Enums\AttractionCategory;
use App\Models\Attraction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * The normalizer is what makes the plan trustworthy whatever produced it,
 * so these double as the evaluation set: each case is a defect an AI
 * answer could contain, and the assertion is the property a guest relies on.
 */
class TripPlanNormalizerTest extends TestCase
{
    use RefreshDatabase;

    private function normalize(array $items, $attractions, ?Carbon $startsOn = null, string $pace = 'balanced', bool $keepOrder = false): array
    {
        return app(TripPlanNormalizer::class)->normalize(
            [['day_number' => 1, 'items' => $items]],
            collect($attractions)->keyBy('id'),
            $startsOn ?? Carbon::parse('2026-11-04'),
            1,
            $pace,
            true,
            $keepOrder,
        )[1] ?? [];
    }

    public function test_a_place_is_never_scheduled_twice(): void
    {
        $a = Attraction::factory()->create();

        $result = $this->normalize([
            ['attraction_id' => $a->id, 'start_time' => '09:00', 'end_time' => '10:00'],
            ['attraction_id' => $a->id, 'start_time' => '12:00', 'end_time' => '13:00'],
        ], [$a]);

        $this->assertCount(1, $result);
    }

    public function test_a_place_closed_on_that_weekday_is_dropped(): void
    {
        // 2026-11-04 is a Wednesday (Carbon dayOfWeek 3).
        $closed = Attraction::factory()->create(['closed_weekdays' => [3]]);
        $open = Attraction::factory()->create();

        $result = $this->normalize([
            ['attraction_id' => $closed->id, 'start_time' => '09:00', 'end_time' => '10:00'],
            ['attraction_id' => $open->id, 'start_time' => '11:00', 'end_time' => '12:00'],
        ], [$closed, $open]);

        $this->assertSame([$open->id], array_column($result, 'attraction_id'));
    }

    public function test_times_are_clamped_to_opening_hours(): void
    {
        $a = Attraction::factory()->create(['opens_at' => '10:00:00', 'closes_at' => '12:00:00', 'avg_visit_minutes' => 60]);

        $result = $this->normalize([['attraction_id' => $a->id, 'start_time' => '08:00', 'end_time' => '09:00']], [$a]);

        $this->assertSame('10:00', $result[0]['start_time']);
        $this->assertSame('11:00', $result[0]['end_time']);
    }

    public function test_a_stop_that_cannot_fit_before_closing_is_dropped(): void
    {
        $a = Attraction::factory()->create(['opens_at' => '09:00:00', 'closes_at' => '12:00:00']);

        $result = $this->normalize([['attraction_id' => $a->id, 'start_time' => '12:30', 'end_time' => '13:30']], [$a]);

        $this->assertSame([], $result);
    }

    public function test_overlapping_stops_are_pushed_apart_by_travel_time(): void
    {
        $a = Attraction::factory()->create(['opens_at' => null, 'closes_at' => null, 'lat' => 34.80, 'lng' => 48.50]);
        $b = Attraction::factory()->create(['opens_at' => null, 'closes_at' => null, 'lat' => 34.78, 'lng' => 48.48, 'avg_visit_minutes' => 30]);

        $result = $this->normalize([
            ['attraction_id' => $a->id, 'start_time' => '09:00', 'end_time' => '10:00'],
            ['attraction_id' => $b->id, 'start_time' => '09:30', 'end_time' => '10:00'],
        ], [$a, $b]);

        $this->assertCount(2, $result);
        $this->assertGreaterThanOrEqual(
            TripPlanNormalizer::toMinutes($result[0]['end_time']) + 15,
            TripPlanNormalizer::toMinutes($result[1]['start_time']),
        );
        $this->assertGreaterThanOrEqual(15, $result[1]['travel_minutes']);
    }

    public function test_the_stops_per_day_limit_follows_the_pace(): void
    {
        $places = Attraction::factory()->count(5)->create(['opens_at' => null, 'closes_at' => null, 'avg_visit_minutes' => 30]);
        $items = $places->map(fn (Attraction $a, int $i) => ['attraction_id' => $a->id, 'start_time' => sprintf('%02d:00', 8 + $i * 2), 'end_time' => sprintf('%02d:30', 8 + $i * 2)])->all();

        $this->assertCount(2, $this->normalize($items, $places, pace: 'relaxed'));
        $this->assertCount(3, $this->normalize($items, $places, pace: 'balanced'));
        $this->assertCount(4, $this->normalize($items, $places, pace: 'packed'));
    }

    public function test_garbage_entries_are_ignored(): void
    {
        $a = Attraction::factory()->create();

        $result = $this->normalize([
            'not an array',
            ['attraction_id' => 'abc', 'start_time' => '09:00'],
            ['attraction_id' => $a->id, 'start_time' => '25:99'],
            ['attraction_id' => $a->id, 'start_time' => '09:00', 'end_time' => '08:00'],
        ], [$a]);

        $this->assertCount(1, $result);
        $this->assertSame('09:00', $result[0]['start_time']);
    }

    public function test_nothing_runs_past_ten_in_the_evening(): void
    {
        $a = Attraction::factory()->create(['opens_at' => null, 'closes_at' => null, 'avg_visit_minutes' => 120]);

        $result = $this->normalize([['attraction_id' => $a->id, 'start_time' => '21:00', 'end_time' => '23:00']], [$a]);

        $this->assertSame('22:00', $result[0]['end_time']);
    }

    public function test_the_rule_based_planner_prefers_the_guests_interests_and_boosted_places(): void
    {
        $here = ['lat' => 34.8, 'lng' => 48.5];
        $nature = Attraction::factory()->create([...$here, 'category' => AttractionCategory::Nature, 'boost_weight' => 0]);
        $shopping = Attraction::factory()->create([...$here, 'category' => AttractionCategory::Shopping, 'boost_weight' => 0]);
        $boosted = Attraction::factory()->create([...$here, 'category' => AttractionCategory::Food, 'boost_weight' => 5]);

        $draft = app(RuleBasedTripPlanner::class)->plan(
            collect([$nature, $shopping, $boosted]),
            Carbon::parse('2026-11-04'),
            1,
            ['interests' => ['nature'], 'pace' => 'relaxed'],
        );

        $ids = array_column($draft['days'][0]['items'], 'attraction_id');

        $this->assertSame([$boosted->id, $nature->id], $ids);
    }

    public function test_the_rule_based_planner_spreads_places_over_days_without_repeats(): void
    {
        $places = Attraction::factory()->count(6)->create(['opens_at' => null, 'closes_at' => null]);

        $draft = app(RuleBasedTripPlanner::class)->plan($places, Carbon::parse('2026-11-04'), 2, ['pace' => 'balanced']);

        $all = collect($draft['days'])->flatMap(fn ($day) => array_column($day['items'], 'attraction_id'));

        $this->assertSame($all->count(), $all->unique()->count());
        $this->assertCount(2, $draft['days']);
    }

    public function test_family_with_kids_prefers_kid_friendly_places(): void
    {
        $quiet = Attraction::factory()->create(['kid_friendly' => false, 'boost_weight' => 1]);
        $playful = Attraction::factory()->create(['kid_friendly' => true, 'boost_weight' => 0]);

        $draft = app(RuleBasedTripPlanner::class)->plan(collect([$quiet, $playful]), Carbon::parse('2026-11-04'), 1, ['pace' => 'relaxed', 'group_type' => 'family_kids']);

        $this->assertSame($playful->id, $draft['days'][0]['items'][0]['attraction_id']);
    }
}
