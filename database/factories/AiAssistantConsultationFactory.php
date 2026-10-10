<?php

namespace Database\Factories;

use App\Models\AiAssistantConsultation;
use App\Models\AiAssistantProfile;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AiAssistantConsultation>
 */
class AiAssistantConsultationFactory extends Factory
{
    protected $model = AiAssistantConsultation::class;

    public function definition(): array
    {
        return [
            'consulting_ai_assistant_profile_id' => AiAssistantProfile::factory(),
            'consulted_ai_assistant_profile_id' => AiAssistantProfile::factory(),
            'question' => fake()->sentence(8),
            'answer' => fake()->paragraph(),
        ];
    }
}
