<?php

namespace Database\Factories;

use App\Enums\AttractionCategory;
use App\Models\Attraction;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Attraction>
 */
class AttractionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => $this->faker->unique()->city().' - '.$this->faker->streetName(),
            'category' => $this->faker->randomElement(AttractionCategory::cases()),
            'description' => $this->faker->sentence(12),
            'lat' => $this->faker->latitude(34.7, 34.9),
            'lng' => $this->faker->longitude(48.4, 48.6),
            'avg_visit_minutes' => $this->faker->randomElement([30, 45, 60, 90, 120]),
            'is_active' => true,
        ];
    }
}
