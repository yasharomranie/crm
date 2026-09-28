<?php

namespace Database\Factories;

use App\Enums\SeoTechnicalIssueSeverity;
use App\Enums\SeoTechnicalIssueType;
use App\Models\SeoTechnicalAudit;
use App\Models\SeoTechnicalAuditIssue;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SeoTechnicalAuditIssue>
 */
class SeoTechnicalAuditIssueFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'seo_technical_audit_id' => SeoTechnicalAudit::factory(),
            'type' => SeoTechnicalIssueType::BrokenLink,
            'severity' => SeoTechnicalIssueSeverity::Warning,
            'url' => $this->faker->url(),
            'details' => $this->faker->sentence(),
        ];
    }
}
