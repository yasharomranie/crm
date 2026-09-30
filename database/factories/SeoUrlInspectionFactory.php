<?php

namespace Database\Factories;

use App\Models\SeoProject;
use App\Models\SeoUrlInspection;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SeoUrlInspection>
 */
class SeoUrlInspectionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $pageUrl = 'https://'.$this->faker->domainName().'/'.$this->faker->slug();

        return [
            'seo_project_id' => SeoProject::factory(),
            'page_url' => $pageUrl,
            'page_url_hash' => sha1($pageUrl),
            'verdict' => 'PASS',
            'robots_txt_state' => 'ALLOWED',
            'indexing_state' => 'INDEXING_ALLOWED',
            'page_fetch_state' => 'SUCCESSFUL',
            'coverage_state' => 'Submitted and indexed',
            'last_crawl_time' => now()->subDays(2),
            'inspected_at' => now(),
        ];
    }
}
