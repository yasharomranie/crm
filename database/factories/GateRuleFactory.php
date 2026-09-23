<?php

namespace Database\Factories;

use App\Models\Gate;
use App\Models\GateRule;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<GateRule>
 */
class GateRuleFactory extends Factory
{
    public function definition(): array
    {
        return [
            'gate_id' => Gate::factory(),
            'is_active' => true,
        ];
    }
}
