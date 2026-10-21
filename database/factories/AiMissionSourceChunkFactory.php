<?php

namespace Database\Factories;

use App\Models\AiMissionSource;
use App\Models\AiMissionSourceChunk;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AiMissionSourceChunk>
 */
class AiMissionSourceChunkFactory extends Factory
{
    protected $model = AiMissionSourceChunk::class;

    public function definition(): array
    {
        return [
            'ai_mission_source_id' => AiMissionSource::factory(),
            'position' => 1,
            'heading' => null,
            'content' => fake()->paragraph(3),
        ];
    }
}
