<?php

namespace Database\Factories;

use App\Models\AiAssistantProfile;
use App\Models\AiAssistantWatchedSource;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AiAssistantWatchedSource>
 */
class AiAssistantWatchedSourceFactory extends Factory
{
    protected $model = AiAssistantWatchedSource::class;

    public function definition(): array
    {
        return [
            'ai_assistant_profile_id' => AiAssistantProfile::factory(),
            'url' => 'https://'.fake()->domainName().'/'.fake()->slug(),
            'label' => fake()->words(2, true),
            'last_content_hash' => null,
            'last_checked_at' => null,
        ];
    }

    public function alreadyChecked(string $hash = 'baseline-hash'): self
    {
        return $this->state([
            'last_content_hash' => $hash,
            'last_checked_at' => now()->subDay(),
        ]);
    }
}
