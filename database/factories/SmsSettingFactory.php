<?php

namespace Database\Factories;

use App\Models\SmsSetting;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SmsSetting>
 */
class SmsSettingFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'is_enabled' => true,
            'api_key' => fake()->uuid(),
            'line_number' => fake()->numerify('#########'),
        ];
    }
}
