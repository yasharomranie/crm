<?php

namespace Database\Factories;

use App\Models\WebAppSection;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WebAppSection>
 */
class WebAppSectionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'title' => $this->faker->words(2, true),
            'sort_order' => 0,
            'is_active' => true,
        ];
    }
}
