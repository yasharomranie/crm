<?php

namespace Database\Factories;

use App\Enums\SeoOpportunityOutcomeVerdict;
use App\Models\SeoOpportunity;
use App\Models\SeoOpportunityOutcome;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SeoOpportunityOutcome>
 */
class SeoOpportunityOutcomeFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'seo_opportunity_id' => SeoOpportunity::factory(),
            'baseline_captured_at' => now(),
            'baseline_clicks' => $this->faker->numberBetween(10, 200),
            'baseline_impressions' => $this->faker->numberBetween(100, 2000),
            'verdict' => SeoOpportunityOutcomeVerdict::Pending,
        ];
    }
}
