<?php

namespace Database\Factories;

use App\Models\SeoInternalLink;
use App\Models\SeoTechnicalAudit;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SeoInternalLink>
 */
class SeoInternalLinkFactory extends Factory
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
            'from_url' => $this->faker->url(),
            'to_url' => $this->faker->url(),
        ];
    }
}
