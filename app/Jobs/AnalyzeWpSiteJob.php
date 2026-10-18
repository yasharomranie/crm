<?php

namespace App\Jobs;

use App\Domain\Seo\WpConnector\WpPublishedContentMatcher;
use App\Domain\Seo\WpConnector\WpSiteAnalyzer;
use App\Models\SeoWpSite;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Re-analyses one connected WordPress site and marks the calendar rows whose article it now has as published. Unique for five minutes, so a
 * burst of synced batches queues a single analysis, not one per batch.
 */
class AnalyzeWpSiteJob implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 120;

    public int $uniqueFor = 300;

    public function __construct(public int $siteId) {}

    public function uniqueId(): string
    {
        return (string) $this->siteId;
    }

    public function handle(WpSiteAnalyzer $analyzer, WpPublishedContentMatcher $matcher): void
    {
        $site = SeoWpSite::with('project')->find($this->siteId);

        if ($site) {
            $analyzer->analyze($site);

            if ($site->project) {
                $matcher->match($site->project);
            }
        }
    }
}
