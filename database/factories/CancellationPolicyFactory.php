<?php

namespace Database\Factories;

use App\Models\CancellationPolicy;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CancellationPolicy>
 */
class CancellationPolicyFactory extends Factory
{
    protected $model = CancellationPolicy::class;

    public function definition(): array
    {
        return [
            'name' => fake()->unique()->words(2, true),
            'late_penalty_percent' => 100,
            'no_show_penalty_percent' => 100,
            'is_default' => false,
            'is_active' => true,
        ];
    }

    /**
     * Cancel at least 7 days ahead: free; 3 days: 30%; 1 day: 70%; later: all.
     */
    public function steps(): static
    {
        return $this->afterCreating(function (CancellationPolicy $policy) {
            foreach ([[7, 0], [3, 30], [1, 70]] as [$days, $percent]) {
                $policy->rules()->create(['min_days_before' => $days, 'penalty_percent' => $percent]);
            }
        });
    }
}
