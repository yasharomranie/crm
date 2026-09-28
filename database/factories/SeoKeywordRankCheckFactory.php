<?php

namespace Database\Factories;

use App\Models\SeoKeywordRankCheck;
use App\Models\SeoTrackedKeyword;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SeoKeywordRankCheck>
 */
class SeoKeywordRankCheckFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'seo_tracked_keyword_id' => SeoTrackedKeyword::factory(),
            'date' => now()->toDateString(),
            'position' => $this->faker->numberBetween(1, 50),
            'checked_at' => now(),
        ];
    }
}
