<?php

namespace Database\Factories;

use App\Enums\AiMissionRoleType;
use App\Models\AiAssistantProfile;
use App\Models\AiMission;
use App\Models\AiMissionRole;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AiMissionRole>
 */
class AiMissionRoleFactory extends Factory
{
    protected $model = AiMissionRole::class;

    public function definition(): array
    {
        return [
            'ai_mission_id' => AiMission::factory(),
            'ai_assistant_profile_id' => AiAssistantProfile::factory(),
            'role' => AiMissionRoleType::Specialist,
            'directive' => fake()->sentence(8),
            'sort' => 0,
        ];
    }
}
