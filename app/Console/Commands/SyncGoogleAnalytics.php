<?php

namespace App\Console\Commands;

use App\Domain\Seo\GoogleAnalyticsSyncService;
use App\Jobs\SyncGoogleAnalyticsJob;
use App\Models\SeoProject;
use Illuminate\Console\Command;

class SyncGoogleAnalytics extends Command
{
    protected $signature = 'seo:sync-google-analytics';

    protected $description = 'Queue a Google Analytics 4 sync for every SEO project that has a GA4 property chosen';

    public function handle(GoogleAnalyticsSyncService $analytics): int
    {
        $queued = SeoProject::query()
            ->with('googleConnection')
            ->where('is_active', true)
            ->whereHas('googleConnection', fn ($connection) => $connection->whereNotNull('ga4_property_id'))
            ->get()
            ->filter(fn (SeoProject $project) => $analytics->isReady($project))
            ->each(fn (SeoProject $project, int $index) => SyncGoogleAnalyticsJob::dispatch($project->id)->delay(now()->addSeconds($index * 15)))
            ->count();

        $this->info("{$queued} project(s) queued.");

        return self::SUCCESS;
    }
}
