<?php

namespace Database\Factories;

use App\Enums\SeoProjectGoal;
use App\Models\SeoProject;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SeoProject>
 */
class SeoProjectFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => $this->faker->company(),
            'website_url' => 'https://'.$this->faker->domainName(),
            'goal' => $this->faker->randomElement(SeoProjectGoal::cases()),
            'audience' => $this->faker->sentence(),
            'market' => 'ایران',
            'brand' => $this->faker->company(),
            'is_active' => true,
        ];
    }
}
