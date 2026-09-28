<?php

namespace Database\Factories;

use App\Models\SeoGoogleSetting;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SeoGoogleSetting>
 */
class SeoGoogleSettingFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'client_id' => fake()->uuid().'.apps.googleusercontent.com',
            'client_secret' => fake()->uuid(),
        ];
    }
}
