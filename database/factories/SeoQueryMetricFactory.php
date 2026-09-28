<?php

namespace Database\Factories;

use App\Models\SeoProject;
use App\Models\SeoQueryMetric;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SeoQueryMetric>
 */
class SeoQueryMetricFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $query = $this->faker->words(3, true);
        $impressions = $this->faker->numberBetween(10, 5000);
        $clicks = $this->faker->numberBetween(0, (int) ($impressions * 0.1));

        return [
            'seo_project_id' => SeoProject::factory(),
            'date' => $this->faker->dateTimeBetween('-3 months', 'now')->format('Y-m-d'),
            'query' => $query,
            'query_hash' => sha1($query),
            'clicks' => $clicks,
            'impressions' => $impressions,
            'ctr' => $impressions > 0 ? round($clicks / $impressions, 4) : 0,
            'position' => $this->faker->randomFloat(2, 1, 60),
        ];
    }
}
