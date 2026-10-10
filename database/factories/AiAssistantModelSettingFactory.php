<?php

namespace Database\Factories;

use App\Models\AiAssistantModelSetting;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AiAssistantModelSetting>
 */
class AiAssistantModelSettingFactory extends Factory
{
    protected $model = AiAssistantModelSetting::class;

    public function definition(): array
    {
        return [
            'assistant_key' => fake()->unique()->slug(2),
            'model' => fake()->randomElement(['gpt-4o', 'gpt-4o-mini', null]),
        ];
    }
}
