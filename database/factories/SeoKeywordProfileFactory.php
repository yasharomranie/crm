<?php

namespace Database\Factories;

use App\Enums\SeoKeywordTrend;
use App\Models\SeoKeywordProfile;
use App\Models\SeoProject;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SeoKeywordProfile>
 */
class SeoKeywordProfileFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $query = $this->faker->words(3, true);

        return [
            'seo_project_id' => SeoProject::factory(),
            'query' => $query,
            'query_hash' => sha1($query),
            'current_position' => $this->faker->randomFloat(2, 1, 60),
            'clicks_28d' => $this->faker->numberBetween(0, 500),
            'impressions_28d' => $this->faker->numberBetween(10, 10000),
            'ctr_28d' => $this->faker->randomFloat(4, 0, 0.2),
            'trend' => SeoKeywordTrend::Stable,
            'landing_page' => 'https://'.$this->faker->domainName().'/'.$this->faker->slug(),
        ];
    }
}
