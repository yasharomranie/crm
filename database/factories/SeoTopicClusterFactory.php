<?php

namespace Database\Factories;

use App\Models\SeoProject;
use App\Models\SeoTopicCluster;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

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
            // Cluster names are unique per project; a random suffix keeps two
            // clusters made in one test from colliding on the same two words.
            'name' => $this->faker->words(2, true).' '.Str::lower(Str::random(5)),
        ];
    }
}
