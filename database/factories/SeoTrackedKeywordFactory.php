<?php

namespace Database\Factories;

use App\Models\SeoProject;
use App\Models\SeoTrackedKeyword;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SeoTrackedKeyword>
 */
class SeoTrackedKeywordFactory extends Factory
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
            'keyword' => $this->faker->words(3, true),
            'is_active' => true,
        ];
    }
}
