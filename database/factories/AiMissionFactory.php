<?php

namespace Database\Factories;

use App\Enums\AiMissionStatus;
use App\Models\AiMission;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AiMission>
 */
class AiMissionFactory extends Factory
{
    protected $model = AiMission::class;

    public function definition(): array
    {
        return [
            'created_by' => User::factory(),
            'title' => fake()->sentence(3),
            'goal' => fake()->sentence(12),
            'status' => AiMissionStatus::Draft,
            'max_rounds' => 3,
            'rounds_run' => 0,
        ];
    }
}
