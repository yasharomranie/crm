<?php

namespace Database\Factories;

use App\Models\Gate;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Gate>
 */
class GateFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->words(2, true).' Gate',
            'code' => strtoupper(fake()->unique()->lexify('GATE-????')),
            'is_active' => true,
        ];
    }
}
