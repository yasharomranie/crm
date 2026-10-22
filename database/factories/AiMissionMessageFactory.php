<?php

namespace Database\Factories;

use App\Enums\AiMissionMessageKind;
use App\Models\AiMissionMessage;
use App\Models\AiMissionThread;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AiMissionMessage>
 */
class AiMissionMessageFactory extends Factory
{
    protected $model = AiMissionMessage::class;

    public function definition(): array
    {
        return [
            'ai_mission_thread_id' => AiMissionThread::factory(),
            'kind' => AiMissionMessageKind::User,
            'body' => fake()->sentence(8),
        ];
    }
}
