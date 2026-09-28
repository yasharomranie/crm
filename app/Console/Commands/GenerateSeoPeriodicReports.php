<?php

namespace App\Console\Commands;

use App\Domain\Seo\PeriodicReportService;
use App\Models\SeoProject;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

#[Signature('seo:generate-periodic-reports')]
#[Description('Generate last month\'s executive SEO report for every active project')]
class GenerateSeoPeriodicReports extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(PeriodicReportService $service): int
    {
        $periodStart = now()->subMonthNoOverflow()->startOfMonth();
        $periodEnd = now()->subMonthNoOverflow()->endOfMonth();

        $projects = SeoProject::query()->where('is_active', true)->get();

        foreach ($projects as $project) {
            try {
                $service->generate($project, $periodStart, $periodEnd);
            } catch (\Throwable $exception) {
                // Best-effort, same as CheckKeywordRankJob - one project's
                // report failing (e.g. an unreachable warehouse query)
                // must never block the rest of the batch.
                Log::warning('GenerateSeoPeriodicReports: failed for one project.', [
                    'seo_project_id' => $project->id,
                    'exception' => $exception->getMessage(),
                ]);
            }
        }

        $this->info("Done. {$projects->count()} project(s) processed for {$periodStart->toDateString()} - {$periodEnd->toDateString()}.");

        return self::SUCCESS;
    }
}
