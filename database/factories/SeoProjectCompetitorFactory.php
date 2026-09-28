<?php

namespace Database\Factories;

use App\Models\SeoProject;
use App\Models\SeoProjectCompetitor;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SeoProjectCompetitor>
 */
class SeoProjectCompetitorFactory extends Factory
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
            'domain' => $this->faker->domainName(),
        ];
    }
}
