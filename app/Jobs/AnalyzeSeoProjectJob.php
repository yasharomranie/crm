<?php

namespace App\Jobs;

use App\Domain\Seo\ContentGapService;
use App\Domain\Seo\KeywordIntelligenceService;
use App\Domain\Seo\OpportunityEngine;
use App\Domain\Seo\UrlOpportunityEngine;
use App\Models\SeoProject;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Runs after SyncSearchConsoleDataJob (chained there, and available as its
 * own manual "تحلیل الان" action): rebuilds keyword profiles from the
 * freshly-synced warehouse, then scans them for opportunities. Kept as a
 * separate job rather than folded into the sync job itself, so re-running
 * analysis alone (e.g. after tuning a detector) never re-hits the Search
 * Console API.
 */
class AnalyzeSeoProjectJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(public int $seoProjectId) {}

    public function handle(KeywordIntelligenceService $keywordIntelligence, OpportunityEngine $opportunityEngine, UrlOpportunityEngine $urlOpportunityEngine, ContentGapService $contentGapService): void
    {
        $project = SeoProject::findOrFail($this->seoProjectId);

        $keywordIntelligence->rebuildProfiles($project);
        $opportunityEngine->scan($project);
        $urlOpportunityEngine->scan($project);
        $contentGapService->detect($project);

        // Best-effort enrichment, not required for the above to be
        // useful - see ClassifySeoKeywordsJob's own doc comment for why a
        // missing AiProvider is left to fail quietly here rather than
        // interrupting analysis.
        ClassifySeoKeywordsJob::dispatch($project->id);
    }
}
