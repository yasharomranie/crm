<?php

namespace Database\Factories;

use App\Models\SeoWpPost;
use App\Models\SeoWpSite;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SeoWpPost>
 */
class SeoWpPostFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $url = 'https://example.test/'.$this->faker->unique()->slug();

        return [
            'seo_wp_site_id' => SeoWpSite::factory()->connected(),
            'wp_id' => $this->faker->unique()->numberBetween(1, 1_000_000),
            'post_type' => 'post',
            'status' => 'publish',
            'url' => $url,
            'url_hash' => SeoWpPost::hashUrl($url),
            'title' => $this->faker->sentence(),
            'word_count' => $this->faker->numberBetween(200, 2000),
            'synced_at' => now(),
        ];
    }
}
