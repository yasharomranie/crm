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

// مرکز سئو "learning" step: re-measures every resolved opportunity whose
// baseline is old enough (OpportunityOutcomeService::FOLLOWUP_WINDOW_DAYS)
// and records whether it actually helped. Daily is plenty - the
// underlying warehouse itself only updates when someone triggers a sync.
Schedule::command('seo:evaluate-opportunity-outcomes')
    ->dailyAt('02:00')
    ->timezone('Asia/Tehran')
    ->withoutOverlapping();

// ترکر کلمه کلیدی: one Google Custom Search query per active tracked
// keyword, once a day - the free tier's 100/day quota is shared across
// the whole app, so this runs once, early, rather than spread through
// the day where it could collide with anything else that calls the same
// API key/quota.
Schedule::command('seo:check-tracked-keyword-ranks')
    ->dailyAt('06:00')
    ->timezone('Asia/Tehran')
    ->withoutOverlapping();

// یک کرال کامل کندتر و سنگین‌تر از چک روزانهٔ رتبه است، پس هفتگی کافی است
// - جمعه سحر (کم‌ترین بازدید سایت‌های ایرانی) تا بار اضافیِ کرال کمترین
// تأثیر را روی سایت واقعی مشتری داشته باشد.
Schedule::command('seo:run-technical-audits')
    ->weeklyOn(5, '03:00')
    ->timezone('Asia/Tehran')
    ->withoutOverlapping();

// اولین روز هر ماه، گزارش اجرایی ماه گذشته را برای هر پروژه می‌سازد - نه
// زودتر، چون هنوز داده‌ی ماه در حال گذر کامل نیست.
Schedule::command('seo:generate-periodic-reports')
    ->monthlyOn(1, '04:00')
    ->timezone('Asia/Tehran')
    ->withoutOverlapping();

// sitemap.xml معمولاً چند بار در روز هم تغییر نمی‌کند، پس یک بار در روز
// برای تشخیص صفحات تازه‌اضافه‌شده/ویرایش‌شده کافی است - سحرگاه، مثل
// همگام‌سازی هفتگیِ آدیت تکنیکال، تا کمترین بار را روی سایت مشتری بگذارد.
Schedule::command('seo:sync-sitemaps')
    ->dailyAt('05:00')
    ->timezone('Asia/Tehran')
    ->withoutOverlapping();
