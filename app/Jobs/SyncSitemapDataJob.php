<?php

namespace App\Jobs;

use App\Domain\Seo\SitemapMonitorService;
use App\Models\SeoProject;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * One project's sitemap sync - kept as its own job (mirrors
 * RunTechnicalSeoAuditJob's one-job-per-subject shape) so a slow or
 * broken sitemap on one project never blocks another's.
 */
class SyncSitemapDataJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    public int $timeout = 180;

    public function __construct(public int $seoProjectId) {}

    public function handle(SitemapMonitorService $service): void
    {
        $service->sync(SeoProject::findOrFail($this->seoProjectId));
    }
}
