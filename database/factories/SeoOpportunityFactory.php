<?php

namespace Database\Factories;

use App\Enums\SeoConfidenceLevel;
use App\Enums\SeoOpportunityPriority;
use App\Enums\SeoOpportunityType;
use App\Models\SeoOpportunity;
use App\Models\SeoProject;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SeoOpportunity>
 */
class SeoOpportunityFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'seo_project_id' => SeoProject::factory(),
            'type' => SeoOpportunityType::CtrOpportunity,
            'title' => $this->faker->sentence(),
            'evidence' => ['note' => $this->faker->sentence()],
            'recommended_actions' => ['review_title'],
            'priority' => SeoOpportunityPriority::Medium,
            'confidence' => SeoConfidenceLevel::Medium,
            'detected_at' => now(),
        ];
    }
}
