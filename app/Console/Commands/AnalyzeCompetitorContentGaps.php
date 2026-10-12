<?php

namespace App\Console\Commands;

use App\Jobs\AnalyzeCompetitorContentGapsJob;
use App\Models\SeoProject;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('seo:analyze-competitor-gaps')]
#[Description('Dispatch a weekly competitor sitemap/content-gap analysis for every active project that has at least one competitor registered')]
class AnalyzeCompetitorContentGaps extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $projects = SeoProject::query()
            ->where('is_active', true)
            ->whereHas('competitors')
            ->get();

        foreach ($projects as $project) {
            AnalyzeCompetitorContentGapsJob::dispatch($project->id);
        }

        $this->info("Done. {$projects->count()} project(s) queued for a competitor content-gap analysis.");

        return self::SUCCESS;
    }
}
