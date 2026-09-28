<?php

namespace App\Console\Commands;

use App\Domain\Seo\OpportunityOutcomeService;
use App\Models\SeoOpportunityOutcome;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('seo:evaluate-opportunity-outcomes')]
#[Description('Re-measure every resolved SEO opportunity whose baseline is old enough and record whether it actually helped')]
class EvaluateSeoOpportunityOutcomes extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(OpportunityOutcomeService $service): int
    {
        $outcomes = SeoOpportunityOutcome::query()
            ->whereNull('followup_measured_at')
            ->where('baseline_captured_at', '<=', now()->subDays(OpportunityOutcomeService::FOLLOWUP_WINDOW_DAYS))
            ->get();

        foreach ($outcomes as $outcome) {
            $service->evaluate($outcome);
        }

        $this->info("Done. {$outcomes->count()} opportunity outcome(s) evaluated.");

        return self::SUCCESS;
    }
}
