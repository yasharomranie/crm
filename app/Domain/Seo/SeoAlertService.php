<?php

namespace App\Domain\Seo;

use App\Enums\SeoOpportunityPriority;
use App\Models\SeoAlertSetting;
use App\Models\SeoOpportunity;
use App\Models\SeoProject;
use App\Models\SeoProjectCompetitor;
use App\Models\SeoTrackedKeyword;
use App\Notifications\SeoCompetitorActivityNotification;
use App\Notifications\SeoGoogleConnectionErrorNotification;
use App\Notifications\SeoOpportunityDetectedNotification;
use App\Notifications\SeoRankDropNotification;
use App\Notifications\SeoSiteHealthNotification;
use Illuminate\Support\Facades\Notification;

/**
 * The single place that decides whether a rank drop or a newly detected
 * opportunity is worth paging someone about, and who - everything else
 * (RankCheckService, OpportunityEngine) just reports what happened and
 * calls in here, the same separation of "measure" vs "notify" that
 * WebhookDispatcher already keeps for external API events.
 */
class SeoAlertService
{
    public function __construct(private readonly TelegramNotifier $telegramNotifier) {}

    public function notifyRankDropIfNeeded(SeoTrackedKeyword $keyword, ?int $previousPosition, ?int $newPosition): void
    {
        $settings = SeoAlertSetting::current();

        if (! $settings->is_enabled) {
            return;
        }

        $fellOutOfTopTen = $previousPosition !== null && $newPosition === null;
        $droppedPastThreshold = $previousPosition !== null && $newPosition !== null
            && ($newPosition - $previousPosition) >= $settings->rank_drop_threshold;

        if (! $fellOutOfTopTen && ! $droppedPastThreshold) {
            return;
        }

        $from = $previousPosition ?? 'خارج از ۱۰ نتیجه اول';
        $to = $newPosition ?? 'خارج از ۱۰ نتیجه اول';
        $this->telegram("📉 افت رتبه\n«{$keyword->keyword}» ({$keyword->project->name}) از رتبه {$from} به {$to} رسید.", $settings);

        $recipients = $settings->recipients();

        if ($recipients->isEmpty()) {
            return;
        }

        Notification::send($recipients, new SeoRankDropNotification($keyword, $previousPosition, $newPosition));
    }

    public function notifyOpportunityDetected(SeoOpportunity $opportunity): void
    {
        $settings = SeoAlertSetting::current();

        if (! $settings->is_enabled || $opportunity->priority !== SeoOpportunityPriority::High) {
            return;
        }

        $this->telegram("🎯 فرصت مهم سئو\n{$opportunity->title} ({$opportunity->project->name})", $settings);

        $recipients = $settings->recipients();

        if ($recipients->isEmpty()) {
            return;
        }

        Notification::send($recipients, new SeoOpportunityDetectedNotification($opportunity));
    }

    /**
     * Unlike the two alerts above, this one has no "is this worth paging
     * someone about" judgment call to make beyond is_enabled - a
     * competitor publishing brand-new pages is inherently a count worth
     * knowing per sync (App\Domain\Seo\CompetitorSitemapCrawler already
     * only calls this when $newPageCount > 0), not something to further
     * filter by severity the way a rank drop or opportunity priority is.
     */
    public function notifyCompetitorActivity(SeoProjectCompetitor $competitor, int $newPageCount): void
    {
        $settings = SeoAlertSetting::current();

        if (! $settings->is_enabled) {
            return;
        }

        $this->telegram("👀 فعالیت رقیب\n{$competitor->domain} {$newPageCount} صفحه‌ی تازه منتشر کرد.", $settings);

        $recipients = $settings->recipients();

        if ($recipients->isEmpty()) {
            return;
        }

        Notification::send($recipients, new SeoCompetitorActivityNotification($competitor, $newPageCount));
    }

    /**
     * Called only on the Connected -> Error transition itself (see
     * SyncSearchConsoleDataJob) - a sync that keeps failing on the same
     * already-known error must never re-page someone every single day
     * it stays broken, the same "only a genuinely new thing pages
     * anyone" rule App\Domain\Seo\OpportunityEngine::
     * recordOrRefreshOpportunity() already applies.
     */
    public function notifyGoogleConnectionError(SeoProject $project, string $error): void
    {
        $settings = SeoAlertSetting::current();

        if (! $settings->is_enabled) {
            return;
        }

        $this->telegram("⚠️ قطع اتصال گوگل سرچ کنسول\nهمگام‌سازی «{$project->name}» متوقف شد: {$error}", $settings);

        $recipients = $settings->recipients();

        if ($recipients->isEmpty()) {
            return;
        }

        Notification::send($recipients, new SeoGoogleConnectionErrorNotification($project, $error));
    }

    /**
     * A site going down, coming back, or a certificate nearing its end. The
     * caller (SiteHealthMonitor) already decided this is worth saying once.
     */
    public function notifySiteHealth(SeoProject $project, string $message): void
    {
        $settings = SeoAlertSetting::current();

        if (! $settings->is_enabled) {
            return;
        }

        $this->telegram($message, $settings);

        $recipients = $settings->recipients();

        if ($recipients->isEmpty()) {
            return;
        }

        Notification::send($recipients, new SeoSiteHealthNotification($project, $message));
    }

    private function telegram(string $text, SeoAlertSetting $settings): void
    {
        if ($settings->telegramIsUsable()) {
            $this->telegramNotifier->send($text, $settings);
        }
    }
}
