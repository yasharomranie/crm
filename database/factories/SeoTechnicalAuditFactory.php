<?php

namespace Database\Factories;

use App\Enums\SeoTechnicalAuditStatus;
use App\Models\SeoProject;
use App\Models\SeoTechnicalAudit;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SeoTechnicalAudit>
 */
class SeoTechnicalAuditFactory extends Factory
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
            'status' => SeoTechnicalAuditStatus::Completed,
            'score' => $this->faker->numberBetween(40, 100),
            'pages_scanned' => $this->faker->numberBetween(5, 40),
            'started_at' => now()->subMinutes(10),
            'completed_at' => now(),
        ];
    }
}
