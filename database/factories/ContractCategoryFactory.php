<?php

namespace Database\Factories;

use App\Models\ContractCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ContractCategory>
 */
class ContractCategoryFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->randomElement([
                'پیمانکاری', 'پشتیبانی سایت', 'مشاوره', 'تأسیسات', 'اجاره',
            ]).' '.fake()->unique()->numerify('###'),
            'ai_guidance' => null,
        ];
    }
}
