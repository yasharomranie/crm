<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Generates today's task instance for every active recurring checklist
// template (e.g. "چک‌لیست نظافت اتاق") shortly after midnight, Tehran time.
Schedule::command('checklists:generate')
    ->dailyAt('00:30')
    ->timezone('Asia/Tehran')
    ->withoutOverlapping();

// Marks everyone with an assigned shift who never checked in today as
// absent, shortly before midnight so a late check-in still counts.
Schedule::command('attendance:mark-absentees')
    ->dailyAt('23:55')
    ->timezone('Asia/Tehran')
    ->withoutOverlapping();

// This host has no persistent queue worker (no Supervisor/terminal access
// to run `queue:work` as a daemon), so QUEUE_CONNECTION=database jobs -
// including every WebPush notification - would otherwise sit in the
// `jobs` table forever. Piggybacking on the existing schedule:run cron
// drains it roughly once a minute instead.
Schedule::command('queue:work --stop-when-empty --max-time=50')
    ->everyMinute()
    ->withoutOverlapping();

// Fires any event reminder whose configured "days before + alarm time"
// has arrived (see EventReminder::remindAt()). Runs often since a
// reminder's alarm time can be any minute of the day, not just a fixed
// daily slot like the two schedules above.
Schedule::command('events:send-reminders')
    ->everyFiveMinutes()
    ->withoutOverlapping();
