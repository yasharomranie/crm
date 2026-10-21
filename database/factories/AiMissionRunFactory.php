<?php

namespace Database\Factories;

use App\Enums\AiMissionStatus;
use App\Models\AiMission;
use App\Models\AiMissionRun;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AiMissionRun>
 */
class AiMissionRunFactory extends Factory
{
    protected $model = AiMissionRun::class;

    public function definition(): array
    {
        return [
            'ai_mission_id' => AiMission::factory(),
            'trigger' => 'manual',
            'round' => 1,
            'status' => AiMissionStatus::Done,
            'summary' => fake()->sentence(8),
            'report' => ['summary' => fake()->sentence(8), 'decisions' => [], 'uncertain' => []],
            'tokens_used' => 1200,
            'finished_at' => now(),
        ];
    }
}
