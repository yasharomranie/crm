<?php

namespace Database\Factories;

use App\Enums\AiMissionSourceKind;
use App\Enums\AiMissionSourceStatus;
use App\Models\AiMission;
use App\Models\AiMissionSource;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AiMissionSource>
 */
class AiMissionSourceFactory extends Factory
{
    protected $model = AiMissionSource::class;

    public function definition(): array
    {
        $content = fake()->paragraph(6);

        return [
            'ai_mission_id' => AiMission::factory(),
            'user_id' => fn (array $attributes) => $attributes['ai_mission_id'] ? AiMission::find($attributes['ai_mission_id'])?->created_by : User::factory(),
            'title' => fake()->words(3, true),
            'original_filename' => 'strategy.txt',
            'extension' => 'txt',
            'kind' => AiMissionSourceKind::Policy,
            'status' => AiMissionSourceStatus::Ready,
            'content' => $content,
            'content_hash' => sha1($content),
            'char_count' => mb_strlen($content),
            'digest' => [
                'summary' => fake()->sentence(8),
                'goals' => ['افزایش نرخ اشغال'],
                'principles' => [],
                'red_lines' => [],
                'kpis' => [],
                'facts' => [],
            ],
            'processed_at' => now(),
        ];
    }
}
