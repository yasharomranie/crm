<?php

namespace App\Jobs;

use App\Domain\Seo\AiKeywordClassifier;
use App\Exceptions\AiProviderException;
use App\Models\SeoProject;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

/**
 * Best-effort enrichment, not part of the critical path: chained after
 * AnalyzeSeoProjectJob so newly-seen keywords pick up intent/business
 * value/topic automatically, but core reporting and the Opportunity Engine
 * work fine without it. A missing AiProvider is swallowed here (and only
 * logged) rather than thrown, because under a sync queue connection a
 * thrown exception would propagate straight back into the job that
 * dispatched this one - see the "تحلیل هوشمند کلمات" manual action for the
 * same call with an immediate, user-facing error instead.
 */
class ClassifySeoKeywordsJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    public function __construct(public int $seoProjectId) {}

    public function handle(AiKeywordClassifier $classifier): void
    {
        try {
            $classifier->classify(SeoProject::findOrFail($this->seoProjectId));
        } catch (AiProviderException $exception) {
            Log::warning('ClassifySeoKeywordsJob: skipped, no active AiProvider.', [
                'seo_project_id' => $this->seoProjectId,
                'exception' => $exception->getMessage(),
            ]);
        }
    }
}
