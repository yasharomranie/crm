<?php

namespace Database\Factories;

use App\Enums\AiMissionActionStatus;
use App\Models\AiMission;
use App\Models\AiMissionAction;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AiMissionAction>
 */
class AiMissionActionFactory extends Factory
{
    protected $model = AiMissionAction::class;

    public function definition(): array
    {
        $title = fake()->sentence(3);

        return [
            'ai_mission_id' => AiMission::factory(),
            'round' => 1,
            'tool' => 'create_task',
            'arguments' => ['title' => $title],
            'summary' => "ایجاد وظیفهٔ «{$title}»",
            'reason' => fake()->sentence(6),
            'status' => AiMissionActionStatus::Pending,
        ];
    }
}
