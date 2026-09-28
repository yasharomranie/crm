<?php

namespace App\Jobs;

use App\Domain\Seo\RankCheckService;
use App\Exceptions\SeoGoogleException;
use App\Models\SeoTrackedKeyword;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

/**
 * One tracked keyword's daily rank check. A missing/unconfigured rank
 * tracker, or a quota/request failure from Google's Custom Search API, is
 * logged rather than thrown - the free tier's shared 100/day quota means
 * later keywords in the same batch can legitimately fail once it's spent,
 * and that must never crash the whole day's check run (see
 * CheckTrackedKeywordRanks, which dispatches one of these per keyword).
 */
class CheckKeywordRankJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    public function __construct(public int $seoTrackedKeywordId) {}

    public function handle(RankCheckService $service): void
    {
        try {
            $service->check(SeoTrackedKeyword::findOrFail($this->seoTrackedKeywordId));
        } catch (SeoGoogleException $exception) {
            Log::warning('CheckKeywordRankJob: rank check failed.', [
                'seo_tracked_keyword_id' => $this->seoTrackedKeywordId,
                'exception' => $exception->getMessage(),
            ]);
        }
    }
}
