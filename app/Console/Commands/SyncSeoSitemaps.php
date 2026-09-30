<?php

namespace App\Console\Commands;

use App\Jobs\SyncSitemapDataJob;
use App\Models\SeoProject;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('seo:sync-sitemaps')]
#[Description('Dispatch a daily sitemap.xml sync for every active project that has a sitemap registered')]
class SyncSeoSitemaps extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $projects = SeoProject::query()
            ->where('is_active', true)
            ->whereNotNull('sitemap_url')
            ->get();

        foreach ($projects as $project) {
            SyncSitemapDataJob::dispatch($project->id);
        }

        $this->info("Done. {$projects->count()} project(s) queued for a sitemap sync.");

        return self::SUCCESS;
    }
}
