<?php

namespace Database\Factories;

use App\Models\CashierShift;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CashierShift>
 */
class CashierShiftFactory extends Factory
{
    protected $model = CashierShift::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'opening_float' => 0,
            'opened_at' => now()->subHours(4),
        ];
    }
}
