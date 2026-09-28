<?php

namespace App\Jobs;

use App\Domain\Seo\AiKeywordDiscoveryService;
use App\Models\SeoProject;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Manual-only ("کشف کلمات کلیدی جدید" action) - unlike ClassifySeoKeywordsJob
 * this never runs automatically, since generating new keyword ideas is a
 * deliberate request, not an enrichment of data that just synced.
 */
class DiscoverSeoKeywordsJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    public function __construct(public int $seoProjectId, public int $count = 20) {}

    public function handle(AiKeywordDiscoveryService $discovery): void
    {
        $discovery->discover(SeoProject::findOrFail($this->seoProjectId), $this->count);
    }
}
