<?php

namespace Database\Factories;

use App\Models\AiAssistantProfile;
use App\Models\AiAssistantScheduledPrompt;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AiAssistantScheduledPrompt>
 */
class AiAssistantScheduledPromptFactory extends Factory
{
    protected $model = AiAssistantScheduledPrompt::class;

    public function definition(): array
    {
        return [
            'ai_assistant_profile_id' => AiAssistantProfile::factory(),
            'prompt' => fake()->sentence(8),
            'run_at_time' => '08:00',
            'is_active' => true,
            'last_run_at' => null,
        ];
    }
}
