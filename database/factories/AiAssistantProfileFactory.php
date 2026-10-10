<?php

namespace Database\Factories;

use App\Models\AiAssistantProfile;
use App\Models\User;
use App\Support\AiAssistantAvatar;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AiAssistantProfile>
 */
class AiAssistantProfileFactory extends Factory
{
    protected $model = AiAssistantProfile::class;

    public function definition(): array
    {
        return [
            'created_by' => User::factory(),
            'name' => fake()->jobTitle(),
            'description' => fake()->sentence(12),
            'avatar_shape' => fake()->randomElement(array_keys(AiAssistantAvatar::SHAPES)),
            'avatar_color' => fake()->randomElement(array_keys(AiAssistantAvatar::COLORS)),
            'is_shared' => false,
        ];
    }
}
