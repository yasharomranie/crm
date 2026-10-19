<?php

namespace App\Jobs;

use App\Domain\Seo\GoogleAnalyticsSyncService;
use App\Exceptions\SeoGoogleException;
use App\Models\SeoProject;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Brings one project's Google Analytics 4 numbers in. A failure is written
 * on the connection (shown on the project page) instead of retried, since
 * the usual causes - a revoked permission, a disabled API - need a person.
 */
class SyncGoogleAnalyticsJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 240;

    public function __construct(public int $seoProjectId) {}

    public function handle(GoogleAnalyticsSyncService $analytics): void
    {
        $project = SeoProject::with('googleConnection')->find($this->seoProjectId);

        if (! $project || ! $analytics->isReady($project)) {
            return;
        }

        try {
            $analytics->sync($project);
        } catch (SeoGoogleException $exception) {
            $project->googleConnection->update(['ga_last_error' => mb_substr($exception->getMessage(), 0, 250)]);
        }
    }
}
