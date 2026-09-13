<?php

namespace Database\Factories;

use App\Models\Company;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Company>
 */
class CompanyFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->company(),
            'national_id' => fake()->numerify('##########'),
            'economic_code' => fake()->numerify('##########'),
            'address' => fake()->address(),
            'representative_name' => fake()->name(),
            'representative_role' => 'مدیرعامل',
        ];
    }
}
