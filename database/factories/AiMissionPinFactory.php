<?php

namespace Database\Factories;

use App\Models\AiMission;
use App\Models\AiMissionPin;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AiMissionPin>
 */
class AiMissionPinFactory extends Factory
{
    protected $model = AiMissionPin::class;

    public function definition(): array
    {
        return [
            'ai_mission_id' => AiMission::factory(),
            'created_by' => User::factory(),
            'text' => fake()->sentence(8),
        ];
    }
}
