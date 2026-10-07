<?php

namespace Database\Factories;

use App\Models\CustomerAppGoogleSetting;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CustomerAppGoogleSetting>
 */
class CustomerAppGoogleSettingFactory extends Factory
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
