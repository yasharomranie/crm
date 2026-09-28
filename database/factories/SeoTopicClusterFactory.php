<?php

namespace Database\Factories;

use App\Models\SeoProject;
use App\Models\SeoTopicCluster;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SeoTopicCluster>
 */
class SeoTopicClusterFactory extends Factory
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
            'name' => $this->faker->words(2, true),
        ];
    }
}
