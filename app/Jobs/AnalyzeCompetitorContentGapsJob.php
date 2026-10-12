<?php

namespace App\Jobs;

use App\Domain\Seo\CompetitorContentGapService;
use App\Domain\Seo\CompetitorSitemapCrawler;
use App\Domain\Seo\CompetitorTopicMatcher;
use App\Domain\Seo\SeoAlertService;
use App\Models\SeoProject;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

/**
 * One project's full competitor-gap pipeline: crawl every competitor's
 * sitemap, classify the new pages into this project's own topic
 * clusters, then detect topics competitors cover that this project
 * doesn't. Kept as three sequential steps in one job (not three jobs)
 * since each step depends on the previous one's data being fresh, and
 * the whole pipeline is already bounded by CompetitorSitemapCrawler's
 * own per-competitor page cap.
 *
 * One competitor's sitemap being unreachable (blocked, no sitemap, a
 * dead domain) must never stop the rest - same tolerance
 * App\Domain\Seo\SitemapClient already has for one broken child sitemap.
 */
class AnalyzeCompetitorContentGapsJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    public int $timeout = 600;

    public function __construct(public int $seoProjectId) {}

    public function handle(CompetitorSitemapCrawler $crawler, CompetitorTopicMatcher $matcher, CompetitorContentGapService $gapService, SeoAlertService $alertService): void
    {
        $project = SeoProject::with('competitors')->findOrFail($this->seoProjectId);

        foreach ($project->competitors as $competitor) {
            try {
                $newPageCount = $crawler->sync($competitor);

                if ($newPageCount > 0) {
                    $alertService->notifyCompetitorActivity($competitor, $newPageCount);
                }
            } catch (\Throwable $exception) {
                Log::warning('AnalyzeCompetitorContentGapsJob: competitor sitemap sync failed.', [
                    'seo_project_competitor_id' => $competitor->id,
                    'domain' => $competitor->domain,
                    'exception' => $exception->getMessage(),
                ]);
            }
        }

        $matcher->match($project);
        $gapService->detect($project);
    }
}
