<?php

namespace App\Jobs;

use App\Domain\Seo\KeywordIntelligenceService;
use App\Domain\Seo\OpportunityEngine;
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

    public function handle(KeywordIntelligenceService $keywordIntelligence, OpportunityEngine $opportunityEngine): void
    {
        $project = SeoProject::findOrFail($this->seoProjectId);

        $keywordIntelligence->rebuildProfiles($project);
        $opportunityEngine->scan($project);
    }
}
