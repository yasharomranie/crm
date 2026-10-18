<?php

namespace Tests\Feature\TripPlanner;

use App\Domain\TripPlanner\TripPlannerReport;
use App\Enums\AiMessageRole;
use App\Filament\Pages\AiAssistant;
use App\Filament\Pages\TripPlannerReportPage;
use App\Models\AiConversation;
use App\Models\AiProvider;
use App\Models\Attraction;
use App\Models\Booking;
use App\Models\Guest;
use App\Models\TripPlan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class TripPlannerReportTest extends TestCase
{
    use RefreshDatabase;

    private function planFor(Guest $guest, array $attributes = []): TripPlan
    {
        return $guest->tripPlans()->create([
            'days' => 2, 'pace' => 'balanced', 'starts_on' => now()->addDays(3)->toDateString(), ...$attributes,
        ]);
    }

    private function stop(TripPlan $plan, Attraction $attraction): void
    {
        $plan->items()->create(['attraction_id' => $attraction->id, 'day_number' => 1, 'start_time' => '09:00', 'end_time' => '10:00', 'sort_order' => 0]);
    }

    public function test_it_summarises_plans_ratings_fallbacks_and_attraction_usage(): void
    {
        $popular = Attraction::factory()->create(['name' => 'پرطرفدار']);
        $ignored = Attraction::factory()->create(['name' => 'فراموش‌شده']);
        $guest = Guest::factory()->create();
        $a = $this->planFor($guest, ['rating' => 5, 'generation_method' => 'ai', 'generation_ms' => 2000, 'share_token' => 'abc']);
        $b = $this->planFor($guest, ['rating' => 3, 'generation_method' => 'fallback', 'generation_ms' => 4000]);
        $this->stop($a, $popular);
        $this->stop($b, $popular);

        $report = app(TripPlannerReport::class)->build(30);

        $this->assertSame(2, $report['plans']);
        $this->assertSame(4.0, $report['average_rating']);
        $this->assertSame(1, $report['fallback_plans']);
        $this->assertSame(3000, $report['average_generation_ms']);
        $this->assertSame(1, $report['shared_plans']);
        $this->assertSame(['name' => 'پرطرفدار', 'count' => 2], $report['top_attractions'][0]);
        $this->assertSame(['فراموش‌شده'], $report['never_scheduled']);
        $this->assertNotContains('پرطرفدار', $report['never_scheduled']);
        $this->assertSame($ignored->name, $report['never_scheduled'][0]);
    }

    public function test_plans_with_a_booking_for_the_planned_dates_count_as_converted(): void
    {
        $converted = Guest::factory()->create();
        $notConverted = Guest::factory()->create();
        $this->planFor($converted, ['starts_on' => now()->addDays(10)->toDateString()]);
        $this->planFor($notConverted, ['starts_on' => now()->addDays(10)->toDateString()]);
        Booking::factory()->create([
            'guest_id' => $converted->id,
            'check_in_date' => now()->addDays(10)->toDateString(),
            'check_out_date' => now()->addDays(12)->toDateString(),
        ]);

        $report = app(TripPlannerReport::class)->build(30);

        $this->assertSame(1, $report['plans_with_booking']);
        $this->assertSame(50.0, $report['booking_conversion_percent']);
    }

    public function test_old_plans_are_outside_the_window(): void
    {
        $this->planFor(Guest::factory()->create())->forceFill(['created_at' => now()->subDays(100)])->save();

        $this->assertSame(0, app(TripPlannerReport::class)->build(30)['plans']);
    }

    public function test_the_report_page_renders_for_an_admin(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole(Role::firstOrCreate(['name' => 'admin']));
        $plan = $this->planFor(Guest::factory()->create());
        $this->stop($plan, Attraction::factory()->create(['name' => 'غار علی‌صدر']));

        Livewire::actingAs($admin)->test(TripPlannerReportPage::class)->assertSee('غار علی‌صدر');
    }

    public function test_the_ai_assistant_can_read_the_summary(): void
    {
        AiProvider::factory()->active()->create();
        $this->artisan('access-control:sync');
        $role = Role::create(['name' => 'مدیر گزارش']);
        $role->givePermissionTo(Permission::whereIn('name', ['ai-assistant.access', 'ai-assistant.report'])->get());
        $user = User::factory()->create();
        $user->assignRole($role);
        $this->actingAs($user);
        $this->planFor(Guest::factory()->create());

        Http::fakeSequence()
            ->push(['choices' => [['message' => [
                'role' => 'assistant', 'content' => null,
                'tool_calls' => [['id' => 'call_1', 'type' => 'function', 'function' => ['name' => 'get_trip_planner_summary', 'arguments' => '{"days":30}']]],
            ]]]])
            ->push(['choices' => [['message' => ['role' => 'assistant', 'content' => 'خلاصه']]]]);

        Livewire::test(AiAssistant::class)->set('body', 'تریپ‌پلنر چطوره؟')->call('send');

        $payload = json_decode(AiConversation::sole()->messages->firstWhere('role', AiMessageRole::Tool)->content, true);

        $this->assertSame(1, $payload['plans']);
    }
}
