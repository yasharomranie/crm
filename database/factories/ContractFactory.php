<?php

namespace Database\Factories;

use App\Models\Contract;
use App\Models\ContractCategory;
use App\Models\ContractCompany;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Contract>
 */
class ContractFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'contract_category_id' => ContractCategory::factory(),
            'contract_company_id' => ContractCompany::factory(),
            'counterparty_name' => fake()->name(),
            'title' => fake()->sentence(3),
            'brief' => fake()->paragraph(),
            'body' => fake()->paragraphs(5, true),
            'audit_warnings' => [],
            'created_by' => User::factory(),
        ];
    }
}
