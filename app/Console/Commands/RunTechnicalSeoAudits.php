<?php

namespace App\Console\Commands;

use App\Jobs\RunTechnicalSeoAuditJob;
use App\Models\SeoProject;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('seo:run-technical-audits')]
#[Description('Dispatch a weekly technical SEO crawl for every active project')]
class RunTechnicalSeoAudits extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $projects = SeoProject::query()->where('is_active', true)->get();

        foreach ($projects as $project) {
            RunTechnicalSeoAuditJob::dispatch($project->id);
        }

        $this->info("Done. {$projects->count()} project(s) queued for a technical SEO audit.");

        return self::SUCCESS;
    }
}
