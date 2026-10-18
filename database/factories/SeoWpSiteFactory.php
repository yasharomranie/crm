<?php

namespace Database\Factories;

use App\Models\SeoProject;
use App\Models\SeoWpSite;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SeoWpSite>
 */
class SeoWpSiteFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'seo_project_id' => SeoProject::factory(),
            'status' => SeoWpSite::STATUS_PENDING,
        ];
    }

    public function connected(): static
    {
        return $this->state(fn () => [
            'status' => SeoWpSite::STATUS_CONNECTED,
            'secret' => bin2hex(random_bytes(32)),
            'site_url' => 'https://example.test',
            'plugin_version' => '1.0.0',
            'scopes' => ['content', 'seo_meta', 'structure', 'images', 'terms'],
            'last_seen_at' => now(),
        ]);
    }
}
