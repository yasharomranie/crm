<?php

namespace Tests\Feature\TripPlanner;

use App\Domain\TripPlanner\TripPlanFollowUp;
use App\Domain\TripPlanner\TripPlannerService;
use App\Enums\TaskType;
use App\Models\Attraction;
use App\Models\Booking;
use App\Models\Guest;
use App\Models\SmsSetting;
use App\Models\Task;
use App\Models\TripPlannerSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class TripPlanCrmIntegrationTest extends TestCase
{
    use RefreshDatabase;

    private function generate(Guest $guest, ?string $startsOn = null)
    {
        return app(TripPlannerService::class)->generate($guest, [
            'days' => 2, 'starts_on' => $startsOn, 'interests' => [], 'pace' => 'balanced',
        ]);
    }

    private function admin(): User
    {
        $admin = User::factory()->create();
        $admin->assignRole(Role::firstOrCreate(['name' => 'admin']));

        return $admin;
    }

    public function test_a_plan_is_recorded_on_the_guests_timeline(): void
    {
        Attraction::factory()->create();
        $guest = Guest::factory()->create();

        $plan = $this->generate($guest);

        $activity = Activity::query()->where('event', 'trip_plan_created')->sole();
        $this->assertSame($guest->id, $activity->subject_id);
        $this->assertSame($plan->id, $activity->properties['trip_plan_id']);
    }

    public function test_no_follow_up_task_is_created_unless_the_setting_is_on(): void
    {
        Attraction::factory()->create();
        $this->admin();

        $this->generate(Guest::factory()->create());

        $this->assertSame(0, Task::where('task_type', TaskType::CrmFollowUp)->count());
    }

    public function test_a_follow_up_task_is_created_for_a_guest_without_a_booking_once_a_week(): void
    {
        Attraction::factory()->create();
        TripPlannerSetting::current()->update(['create_followup_tasks' => true, 'plans_per_day_limit' => 20]);
        $admin = $this->admin();
        $guest = Guest::factory()->create();

        $this->generate($guest);
        app(TripPlanFollowUp::class)->afterPlanCreated($guest->tripPlans()->first());

        $task = Task::where('task_type', TaskType::CrmFollowUp)->sole();
        $this->assertSame($admin->id, $task->assigned_to);
        $this->assertSame($guest->id, $task->source_id);
    }

    public function test_no_follow_up_is_needed_when_the_guest_already_booked_the_dates(): void
    {
        Attraction::factory()->create();
        TripPlannerSetting::current()->update(['create_followup_tasks' => true]);
        $this->admin();
        $guest = Guest::factory()->create();
        Booking::factory()->create([
            'guest_id' => $guest->id,
            'check_in_date' => now()->addDay()->toDateString(),
            'check_out_date' => now()->addDays(4)->toDateString(),
        ]);

        $this->generate($guest, now()->addDays(2)->toDateString());

        $this->assertSame(0, Task::where('task_type', TaskType::CrmFollowUp)->count());
    }

    public function test_the_day_before_reminder_is_sent_once_and_only_when_enabled(): void
    {
        Http::fake(['*' => Http::response(['status' => 1], 200)]);
        SmsSetting::factory()->create(['id' => 1, 'is_enabled' => true]);
        Attraction::factory()->create();
        $guest = Guest::factory()->create(['mobile' => '09120000055']);
        $plan = $this->generate($guest, now()->addDay()->toDateString());
        $service = app(TripPlanFollowUp::class);

        $this->assertSame(0, $service->sendDayBeforeReminders());

        TripPlannerSetting::current()->update(['send_reminders' => true]);

        $this->assertSame(1, $service->sendDayBeforeReminders());
        $this->assertSame(0, $service->sendDayBeforeReminders());
        $this->assertNotNull($plan->fresh()->reminder_sent_at);
    }

    public function test_a_trip_starting_later_gets_no_reminder_yet(): void
    {
        Attraction::factory()->create();
        TripPlannerSetting::current()->update(['send_reminders' => true]);
        $this->generate(Guest::factory()->create(['mobile' => '09120000056']), now()->addDays(5)->toDateString());

        $this->assertSame(0, app(TripPlanFollowUp::class)->sendDayBeforeReminders());
    }
}
