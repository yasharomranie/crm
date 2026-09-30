<?php

namespace Database\Factories;

use App\Models\SeoProject;
use App\Models\SeoSitemapEntry;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SeoSitemapEntry>
 */
class SeoSitemapEntryFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $url = 'https://'.$this->faker->domainName().'/'.$this->faker->slug();
        $now = now();

        return [
            'seo_project_id' => SeoProject::factory(),
            'url' => $url,
            'url_hash' => sha1($url),
            'lastmod' => $now,
            'title' => $this->faker->sentence(4),
            'first_seen_at' => $now,
            'last_seen_at' => $now,
            'last_changed_at' => $now,
        ];
    }
}
