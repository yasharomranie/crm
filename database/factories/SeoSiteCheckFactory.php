<?php

namespace Database\Factories;

use App\Models\SeoProject;
use App\Models\SeoSiteCheck;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SeoSiteCheck>
 */
class SeoSiteCheckFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'seo_project_id' => SeoProject::factory(),
            'is_up' => true,
            'http_status' => 200,
            'response_ms' => 300,
            'ssl_expires_at' => now()->addDays(60),
            'checked_at' => now(),
        ];
    }
}
