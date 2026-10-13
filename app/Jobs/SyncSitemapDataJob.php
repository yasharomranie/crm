<?php

namespace App\Jobs;

use App\Domain\Seo\OwnSitemapTopicMatcher;
use App\Domain\Seo\SitemapMonitorService;
use App\Models\SeoProject;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

/**
 * One project's sitemap sync, then classification of whatever's newly
 * unclassified - kept as two sequential steps in one job (mirrors
 * AnalyzeCompetitorContentGapsJob's sync-then-match sequencing) so a
 * slow or broken sitemap on one project never blocks another's.
 *
 * The Google-status check after them is best-effort: plenty of projects
 * sync their own sitemap with no Google connection at all (or a stale
 * one), and that must never fail a sync that otherwise worked fine -
 * same reasoning TechnicalSeoAuditor already applies to its own
 * PageSpeed check.
 */
class SyncSitemapDataJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    public int $timeout = 180;

    public function __construct(public int $seoProjectId) {}

    public function handle(SitemapMonitorService $service, OwnSitemapTopicMatcher $matcher): void
    {
        $project = SeoProject::findOrFail($this->seoProjectId);

        $service->sync($project);
        $matcher->match($project);

        try {
            $service->checkGoogleStatus($project);
        } catch (\Throwable $exception) {
            Log::warning('SyncSitemapDataJob: Google sitemap status check failed.', [
                'seo_project_id' => $project->id,
                'exception' => $exception->getMessage(),
            ]);
        }
    }
}
