<?php

namespace App\Domain\Crm;

use App\Enums\DealStage;
use App\Models\Deal;
use App\Models\User;
use App\Notifications\StaleDealsDigestNotification;
use Illuminate\Support\Facades\DB;

/**
 * Pushes each deal owner one digest of their open deals that have gone
 * quiet - nobody assigned means every admin gets it. A deal is only
 * flagged once per idle stretch: stale_notified_at is compared with
 * updated_at, so a deal someone has touched since the last alert can be
 * flagged again later, while one nobody touched is not nagged about daily.
 */
class StaleDealAlertService
{
    /** Same threshold the pipeline page's «معاملات راکد» widget uses. */
    public const STALE_AFTER_DAYS = 14;

    /**
     * @return int how many deals were included in alerts
     */
    public function notifyStaleDeals(): int
    {
        $deals = Deal::query()
            ->whereNotIn('stage', [DealStage::Won, DealStage::Lost])
            ->where('updated_at', '<=', now()->subDays(self::STALE_AFTER_DAYS))
            ->where(fn ($query) => $query
                ->whereNull('stale_notified_at')
                ->orWhereColumn('stale_notified_at', '<', 'updated_at'))
            ->with('assignee')
            ->get();

        if ($deals->isEmpty()) {
            return 0;
        }

        $admins = User::query()->whereHas('roles', fn ($query) => $query->where('name', 'admin'))->get();

        $recipientBuckets = [];

        foreach ($deals as $deal) {
            $recipients = $deal->assignee ? collect([$deal->assignee]) : $admins;

            foreach ($recipients as $recipient) {
                $recipientBuckets[$recipient->id]['user'] = $recipient;
                $recipientBuckets[$recipient->id]['titles'][] = $deal->title;
            }
        }

        foreach ($recipientBuckets as $bucket) {
            $bucket['user']->notify(new StaleDealsDigestNotification(count($bucket['titles']), $bucket['titles']));
        }

        // Written straight to the table: Eloquent's update() would bump
        // updated_at and make the deal look freshly touched.
        DB::table('deals')->whereIn('id', $deals->pluck('id'))->update(['stale_notified_at' => now()]);

        return $deals->count();
    }
}
