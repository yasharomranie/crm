<?php

namespace App\Domain\Seo;

use App\Enums\SeoOpportunityPriority;
use App\Models\SeoAlertSetting;
use App\Models\SeoOpportunity;
use App\Models\SeoProjectCompetitor;
use App\Models\SeoTrackedKeyword;
use App\Notifications\SeoCompetitorActivityNotification;
use App\Notifications\SeoOpportunityDetectedNotification;
use App\Notifications\SeoRankDropNotification;
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

        $recipients = $settings->recipients();

        if ($recipients->isEmpty()) {
            return;
        }

        Notification::send($recipients, new SeoCompetitorActivityNotification($competitor, $newPageCount));
    }
}
