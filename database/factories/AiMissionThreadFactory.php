<?php

namespace Database\Factories;

use App\Enums\AiMissionThreadStatus;
use App\Models\AiMission;
use App\Models\AiMissionThread;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AiMissionThread>
 */
class AiMissionThreadFactory extends Factory
{
    protected $model = AiMissionThread::class;

    public function definition(): array
    {
        return [
            'ai_mission_id' => AiMission::factory(),
            'created_by' => User::factory(),
            'title' => fake()->sentence(4),
            'status' => AiMissionThreadStatus::Idle,
        ];
    }
}
