<?php

namespace App\Console\Commands;

use App\Enums\SeoConnectionStatus;
use App\Jobs\SyncSearchConsoleDataJob;
use App\Models\SeoProject;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Every other مرکز سئو data source (sitemap sync, competitor gaps,
 * technical audits) already runs on its own schedule - Search Console
 * sync was the one left manual-only (the "همگام‌سازی الان" button on
 * App\Filament\Resources\SeoProjects\Pages\EditSeoProject), which meant
 * SeoKeywordProfile and everything built on it (AnalyzeSeoProjectJob,
 * the opportunity engine, keyword-discovery's own context) silently went
 * stale for any project whose owner forgot to click it.
 */
#[Signature('seo:sync-search-console')]
#[Description('Dispatch a daily Search Console data sync for every active project with a connected GSC property')]
class SyncSeoSearchConsole extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $projects = SeoProject::query()
            ->where('is_active', true)
            ->whereHas('googleConnection', fn ($query) => $query
                ->where('status', SeoConnectionStatus::Connected)
                ->whereNotNull('gsc_site_url'))
            ->get();

        foreach ($projects as $project) {
            SyncSearchConsoleDataJob::dispatch($project->id);
        }

        $this->info("Done. {$projects->count()} project(s) queued for a Search Console sync.");

        return self::SUCCESS;
    }
}
