<?php

namespace Database\Factories;

use App\Models\WorkShift;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WorkShift>
 */
class WorkShiftFactory extends Factory
{
    protected $model = WorkShift::class;

    public function definition(): array
    {
        return [
            'name' => 'شیفت اداری',
            'start_time' => '09:00:00',
            'end_time' => '17:00:00',
            'grace_minutes' => 15,
        ];
    }
}
