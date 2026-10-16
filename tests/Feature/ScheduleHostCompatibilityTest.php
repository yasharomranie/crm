<?php

namespace Tests\Feature;

use Illuminate\Console\Scheduling\CallbackEvent;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Contracts\Console\Kernel;
use Tests\TestCase;

class ScheduleHostCompatibilityTest extends TestCase
{
    /**
     * The production host has proc_open disabled. A Schedule::command()
     * event spawns a subprocess through Symfony Process and therefore
     * fails there on every run; a Schedule::call() closure runs inside the
     * schedule:run process and doesn't. Every scheduled task must be the
     * latter.
     */
    public function test_every_scheduled_task_runs_in_process_instead_of_spawning_a_subprocess(): void
    {
        $this->app->make(Kernel::class)->bootstrap();

        $events = collect($this->app->make(Schedule::class)->events());

        $this->assertNotEmpty($events);

        $subprocessEvents = $events
            ->reject(fn ($event) => $event instanceof CallbackEvent)
            ->map(fn ($event) => $event->command)
            ->values()
            ->all();

        $this->assertSame([], $subprocessEvents, 'These scheduled tasks spawn a subprocess and would fail on a host without proc_open.');
    }

    public function test_every_scheduled_task_has_a_name_so_its_overlap_lock_can_be_taken(): void
    {
        $this->app->make(Kernel::class)->bootstrap();

        $unnamed = collect($this->app->make(Schedule::class)->events())
            ->filter(fn (CallbackEvent $event) => $event->description === null || $event->description === '')
            ->count();

        $this->assertSame(0, $unnamed);
    }
}
