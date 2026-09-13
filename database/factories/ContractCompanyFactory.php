<?php

namespace Database\Factories;

use App\Models\ContractCompany;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ContractCompany>
 */
class ContractCompanyFactory extends Factory
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
