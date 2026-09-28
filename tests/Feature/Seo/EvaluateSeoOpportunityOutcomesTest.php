<?php

namespace Tests\Feature\Seo;

use App\Domain\Seo\OpportunityOutcomeService;
use App\Enums\SeoOpportunityOutcomeVerdict;
use App\Models\SeoOpportunity;
use App\Models\SeoOpportunityOutcome;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EvaluateSeoOpportunityOutcomesTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_evaluates_only_outcomes_whose_baseline_is_old_enough(): void
    {
        $due = SeoOpportunityOutcome::factory()->create([
            'seo_opportunity_id' => SeoOpportunity::factory(),
            'baseline_captured_at' => now()->subDays(OpportunityOutcomeService::FOLLOWUP_WINDOW_DAYS + 1),
        ]);
        $tooRecent = SeoOpportunityOutcome::factory()->create([
            'seo_opportunity_id' => SeoOpportunity::factory(),
            'baseline_captured_at' => now()->subDays(3),
        ]);
        $alreadyMeasured = SeoOpportunityOutcome::factory()->create([
            'seo_opportunity_id' => SeoOpportunity::factory(),
            'baseline_captured_at' => now()->subDays(OpportunityOutcomeService::FOLLOWUP_WINDOW_DAYS + 1),
            'followup_measured_at' => now(),
        ]);

        $this->artisan('seo:evaluate-opportunity-outcomes')->assertSuccessful();

        $this->assertNotNull($due->fresh()->followup_measured_at);
        $this->assertNull($tooRecent->fresh()->followup_measured_at);
        $this->assertSame(
            $alreadyMeasured->followup_measured_at->toDateTimeString(),
            $alreadyMeasured->fresh()->followup_measured_at->toDateTimeString(),
        );
    }

    public function test_a_due_outcome_with_no_metrics_at_all_is_recorded_as_unchanged(): void
    {
        $outcome = SeoOpportunityOutcome::factory()->create([
            'seo_opportunity_id' => SeoOpportunity::factory(),
            'baseline_captured_at' => now()->subDays(OpportunityOutcomeService::FOLLOWUP_WINDOW_DAYS + 1),
            'baseline_clicks' => 0,
        ]);

        $this->artisan('seo:evaluate-opportunity-outcomes');

        $this->assertSame(SeoOpportunityOutcomeVerdict::Unchanged, $outcome->fresh()->verdict);
    }
}
