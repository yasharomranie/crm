<?php

use App\Domain\Ai\AssistantAlertDigestService;
use App\Domain\Ai\AssistantRetention;
use App\Domain\Crm\BookingMessenger;
use App\Domain\Crm\FollowUpReminderService;
use App\Domain\Crm\LeadScorer;
use App\Domain\Crm\StaleDealAlertService;
use App\Domain\Crm\TicketSlaBreachService;
use App\Domain\Crm\WalletExpiryService;
use App\Domain\Ticketing\GateAnomalyDetector;
use App\Domain\TripPlanner\TripPlanFollowUp;
use App\Jobs\AnalyzeWpSiteJob;
use App\Models\Booking;
use App\Models\SeoWpSite;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Every entry below is a Schedule::call() closure that runs its artisan
// command *inside* the schedule:run process (Artisan::call), never
// Schedule::command(). A command event spawns a separate `php artisan ...`
// subprocess through Symfony Process - and this host has proc_open
// disabled, so every such event just threw "The Process class relies on
// proc_open" in production and never ran. A callback event needs no
// subprocess. tests/Feature/ScheduleHostCompatibilityTest.php guards this.
$artisan = fn (string $command) => fn (): bool => Artisan::call($command) === 0;

// Generates today's task instance for every active recurring checklist
// template (e.g. "چک‌لیست نظافت اتاق") shortly after midnight, Tehran time.
Schedule::call($artisan('checklists:generate'))
    ->name('checklists:generate')
    ->dailyAt('00:30')
    ->timezone('Asia/Tehran')
    ->withoutOverlapping();

// Marks everyone with an assigned shift who never checked in today as
// absent, shortly before midnight so a late check-in still counts.
Schedule::call($artisan('attendance:mark-absentees'))
    ->name('attendance:mark-absentees')
    ->dailyAt('23:55')
    ->timezone('Asia/Tehran')
    ->withoutOverlapping();

// This host has no persistent queue worker (no Supervisor/terminal access
// to run `queue:work` as a daemon), so QUEUE_CONNECTION=database jobs -
// including every WebPush notification - would otherwise sit in the
// `jobs` table forever. Piggybacking on the existing schedule:run cron
// drains it roughly once a minute instead.
Schedule::call($artisan('queue:work --stop-when-empty --max-time=50'))
    ->name('queue:work --stop-when-empty --max-time=50')
    ->everyMinute()
    ->withoutOverlapping();

// Fires any event reminder whose configured "days before + alarm time"
// has arrived (see EventReminder::remindAt()). Runs often since a
// reminder's alarm time can be any minute of the day, not just a fixed
// daily slot like the two schedules above.
Schedule::call($artisan('events:send-reminders'))
    ->name('events:send-reminders')
    ->everyFiveMinutes()
    ->withoutOverlapping();

// Pushes a reminder to the assignee of every CRM follow-up whose due time
// has arrived.
Schedule::call(fn () => app(FollowUpReminderService::class)->sendDueReminders())
    ->name('crm-follow-up-reminders')
    ->everyFiveMinutes()
    ->withoutOverlapping();

// Alerts the assignee (or the admins, if unassigned) the first time an open
// support ticket passes its SLA deadline.
Schedule::call(fn () => app(TicketSlaBreachService::class)->notifyBreachedTickets())
    ->name('crm-ticket-sla-breaches')
    ->everyTenMinutes()
    ->withoutOverlapping();

// Takes back the unspent part of cashback whose validity has ended, then
// texts guests whose cashback is about to run out.
Schedule::call(function () {
    app(WalletExpiryService::class)->expireDue();
    app(WalletExpiryService::class)->sendExpiryReminders();
})
    ->name('wallet-expiry')
    ->dailyAt('03:30')
    ->timezone('Asia/Tehran')
    ->withoutOverlapping();

// Re-scores every open deal each night: the score depends on how long a
// deal has been quiet, which changes without anyone saving the deal.
Schedule::call(fn () => app(LeadScorer::class)->refreshOpenDeals())
    ->name('crm-lead-scores')
    ->dailyAt('02:30')
    ->timezone('Asia/Tehran')
    ->withoutOverlapping();

// Blanks the stored raw output of old assistant tool calls (personal and
// financial data) once it is past the retention period.
Schedule::call(fn () => app(AssistantRetention::class)->pruneToolOutput())
    ->name('assistant-tool-output-retention')
    ->dailyAt('03:15')
    ->timezone('Asia/Tehran')
    ->withoutOverlapping();

// One morning push of the management assistant's critical alerts (an
// over-budget project, a breached support SLA) to whoever may use it.
Schedule::call(fn () => app(AssistantAlertDigestService::class)->notifyDailyDigest())
    ->name('assistant-alert-digest')
    ->dailyAt('08:30')
    ->timezone('Asia/Tehran')
    ->withoutOverlapping();

// Alerts the admins about a ticket rejected again and again at the gates
// (a copied QR code, most likely).
Schedule::call(fn () => app(GateAnomalyDetector::class)->notifyAdmins())
    ->name('gate-anomaly-alerts')
    ->everyFiveMinutes()
    ->withoutOverlapping();

// Texts guests whose planned trip starts tomorrow (only when enabled in the
// trip planner settings).
Schedule::call(fn () => app(TripPlanFollowUp::class)->sendDayBeforeReminders())
    ->name('trip-plan-reminders')
    ->dailyAt('09:00')
    ->timezone('Asia/Tehran')
    ->withoutOverlapping();

// One morning digest per deal owner (admins for unassigned deals) of open
// deals that have gone quiet for two weeks.
Schedule::call(fn () => app(StaleDealAlertService::class)->notifyStaleDeals())
    ->name('crm-stale-deal-digest')
    ->dailyAt('09:00')
    ->timezone('Asia/Tehran')
    ->withoutOverlapping();

// همگام‌سازی گوگل سرچ کنسول - تا امروز تنها راهش دکمه‌ی دستی "همگام‌سازی
// الان" روی هر پروژه بود، در حالی که همهٔ تحلیل‌های زیرش (AnalyzeSeoProjectJob،
// موتور فرصت‌ها، context کشف کلمه کلیدی) به تازگی همین داده وابسته‌اند. زود،
// پیش از بقیهٔ کارهای زمان‌بندی‌شدهٔ سئو، تا آن‌ها هم داده‌ی تازه ببینند.
// SyncSearchConsoleDataJob خودش idempotent است، پس اجرای روزانه ریسکی ندارد.
Schedule::call($artisan('seo:sync-search-console'))
    ->name('seo:sync-search-console')
    ->dailyAt('01:00')
    ->timezone('Asia/Tehran')
    ->withoutOverlapping();

// آمار Google Analytics (نشست، تبدیل، ورودی از هوش مصنوعی) بعد از همگام‌سازی
// Search Console. کارها با فاصله در صف می‌روند تا با ۱۰۰ سایت فشار نیاید.
Schedule::call($artisan('seo:sync-google-analytics'))
    ->name('seo:sync-google-analytics')
    ->dailyAt('02:00')
    ->timezone('Asia/Tehran')
    ->withoutOverlapping();

// مرکز سئو "learning" step: re-measures every resolved opportunity whose
// baseline is old enough (OpportunityOutcomeService::FOLLOWUP_WINDOW_DAYS)
// and records whether it actually helped. Daily is plenty - the
// underlying warehouse itself only updates when someone triggers a sync.
Schedule::call($artisan('seo:evaluate-opportunity-outcomes'))
    ->name('seo:evaluate-opportunity-outcomes')
    ->dailyAt('02:00')
    ->timezone('Asia/Tehran')
    ->withoutOverlapping();

// ترکر کلمه کلیدی: one Google Custom Search query per active tracked
// keyword, once a day - the free tier's 100/day quota is shared across
// the whole app, so this runs once, early, rather than spread through
// the day where it could collide with anything else that calls the same
// API key/quota.
Schedule::call($artisan('seo:check-tracked-keyword-ranks'))
    ->name('seo:check-tracked-keyword-ranks')
    ->dailyAt('06:00')
    ->timezone('Asia/Tehran')
    ->withoutOverlapping();

// یک کرال کامل کندتر و سنگین‌تر از چک روزانهٔ رتبه است، پس هفتگی کافی است
// - جمعه سحر (کم‌ترین بازدید سایت‌های ایرانی) تا بار اضافیِ کرال کمترین
// تأثیر را روی سایت واقعی مشتری داشته باشد.
Schedule::call($artisan('seo:run-technical-audits'))
    ->name('seo:run-technical-audits')
    ->weeklyOn(5, '03:00')
    ->timezone('Asia/Tehran')
    ->withoutOverlapping();

// اولین روز هر ماه، گزارش اجرایی ماه گذشته را برای هر پروژه می‌سازد - نه
// زودتر، چون هنوز داده‌ی ماه در حال گذر کامل نیست.
Schedule::call($artisan('seo:generate-periodic-reports'))
    ->name('seo:generate-periodic-reports')
    ->monthlyOn(1, '04:00')
    ->timezone('Asia/Tehran')
    ->withoutOverlapping();

// هر ربع ساعت می‌پرسد سایت‌ها بالا هستند یا نه و تاریخ SSL را می‌خواند؛
// فقط تغییرِ تازه (قطع‌شدن، وصل‌شدن، نزدیک‌شدن انقضا) هشدار می‌دهد.
Schedule::call($artisan('seo:check-site-health'))
    ->name('seo:check-site-health')
    ->everyFifteenMinutes()
    ->withoutOverlapping();

// sitemap.xml معمولاً چند بار در روز هم تغییر نمی‌کند، پس یک بار در روز
// برای تشخیص صفحات تازه‌اضافه‌شده/ویرایش‌شده کافی است - سحرگاه، مثل
// همگام‌سازی هفتگیِ آدیت تکنیکال، تا کمترین بار را روی سایت مشتری بگذارد.
Schedule::call($artisan('seo:sync-sitemaps'))
    ->name('seo:sync-sitemaps')
    ->dailyAt('05:00')
    ->timezone('Asia/Tehran')
    ->withoutOverlapping();

// sitemap رقبا معمولاً از خودمان هم کمتر تغییر می‌کند و کرالش (چند
// دامنه‌ی خارجی + طبقه‌بندی هوشمند) پرهزینه‌تر از sync سایت خودمان است -
// هفتگی، شنبه سحر، تا با کرال هفتگیِ آدیت تکنیکال (جمعه) هم‌زمان نشود.
Schedule::call($artisan('seo:analyze-competitor-gaps'))
    ->name('seo:analyze-competitor-gaps')
    ->weeklyOn(6, '03:00')
    ->timezone('Asia/Tehran')
    ->withoutOverlapping();

// فاز دوم نالج‌بیس دستیارهای هاب: صفحاتی که مالک یک دستیار برای رصد
// تغییرات ثبت کرده را روزی یک‌بار دوباره می‌خواند و در صورت تغییر محتوا،
// یک نکتهٔ پیشنهادی جدید (در انتظار تأیید) می‌سازد - بدون هیچ تماسی با
// ارائه‌دهندهٔ هوش مصنوعی، پس هزینه‌ای به سهمیهٔ مشترک چت تحمیل نمی‌کند.
Schedule::call($artisan('ai-assistants:check-watched-sources'))
    ->name('ai-assistants:check-watched-sources')
    ->dailyAt('04:30')
    ->timezone('Asia/Tehran')
    ->withoutOverlapping();

// اجرای خودکار روزانهٔ دستورهای زمان‌بندی‌شدهٔ دستیارهای هاب - چون زمان هر
// دستور را خودِ مالک آزادانه انتخاب می‌کند (نه یک ساعت ثابت سراسری)، این
// دستور هر ۱۵ دقیقه بررسی می‌کند که آیا زمان کدام دستور فرارسیده و امروز
// هنوز اجرا نشده است.
// Management-assistant reports a manager asked to receive every day.
Schedule::call($artisan('ai-assistant:run-scheduled-reports'))
    ->name('ai-assistant:run-scheduled-reports')
    ->everyFifteenMinutes()
    ->timezone('Asia/Tehran')
    ->withoutOverlapping();

Schedule::call($artisan('ai-assistants:run-scheduled-prompts'))
    ->name('ai-assistants:run-scheduled-prompts')
    ->everyFifteenMinutes()
    ->timezone('Asia/Tehran')
    ->withoutOverlapping();

// Re-checks every connected WordPress site each morning (new Search Console
// numbers, links, problems); sites are staggered so the queue is not hit all
// at once.
Schedule::call(function () {
    SeoWpSite::query()->where('status', SeoWpSite::STATUS_CONNECTED)->get()
        ->each(fn (SeoWpSite $site, int $index) => AnalyzeWpSiteJob::dispatch($site->id)->delay(now()->addSeconds($index * 20)));
})
    ->name('seo-wp-analyze')
    ->dailyAt('07:30')
    ->timezone('Asia/Tehran')
    ->withoutOverlapping();

// Texts confirmed guests who arrive within the configured number of days
// (only when enabled in «پیام‌های رزرو»); each booking is reminded once.
Schedule::call(fn () => app(BookingMessenger::class)->sendDueArrivalReminders())
    ->name('booking-arrival-reminders')
    ->dailyAt('09:30')
    ->timezone('Asia/Tehran')
    ->withoutOverlapping();

// Releases pending bookings whose hold (set when the booking was made)
// has run out with no money received, so held rooms go back on sale.
Schedule::call(fn () => Booking::releaseExpiredHolds())
    ->name('booking-release-expired-holds')
    ->everyTenMinutes()
    ->withoutOverlapping();
