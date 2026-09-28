<?php

namespace Database\Factories;

use App\Models\SeoDeviceMetric;
use App\Models\SeoProject;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SeoDeviceMetric>
 */
class SeoDeviceMetricFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $impressions = $this->faker->numberBetween(10, 5000);
        $clicks = $this->faker->numberBetween(0, (int) ($impressions * 0.1));

        return [
            'seo_project_id' => SeoProject::factory(),
            'date' => $this->faker->dateTimeBetween('-3 months', 'now')->format('Y-m-d'),
            'device' => $this->faker->randomElement(['DESKTOP', 'MOBILE', 'TABLET']),
            'clicks' => $clicks,
            'impressions' => $impressions,
            'ctr' => $impressions > 0 ? round($clicks / $impressions, 4) : 0,
            'position' => $this->faker->randomFloat(2, 1, 60),
        ];
    }
}
