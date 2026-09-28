<?php

namespace Database\Factories;

use App\Enums\SeoConnectionStatus;
use App\Models\SeoGoogleConnection;
use App\Models\SeoProject;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SeoGoogleConnection>
 */
class SeoGoogleConnectionFactory extends Factory
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
            'google_account_email' => $this->faker->safeEmail(),
            'access_token' => $this->faker->uuid(),
            'refresh_token' => $this->faker->uuid(),
            'token_expires_at' => now()->addHour(),
            'granted_scopes' => ['https://www.googleapis.com/auth/webmasters.readonly'],
            'gsc_site_url' => 'https://'.$this->faker->domainName().'/',
            'status' => SeoConnectionStatus::Connected,
        ];
    }
}
