<?php

namespace Database\Factories;

use App\Enums\AiMissionCardKind;
use App\Enums\AiMissionConfidence;
use App\Enums\AiMissionRoleType;
use App\Models\AiAssistantProfile;
use App\Models\AiMission;
use App\Models\AiMissionCard;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AiMissionCard>
 */
class AiMissionCardFactory extends Factory
{
    protected $model = AiMissionCard::class;

    public function definition(): array
    {
        return [
            'ai_mission_id' => AiMission::factory(),
            'ai_assistant_profile_id' => AiAssistantProfile::factory(),
            'role' => AiMissionRoleType::Specialist,
            'round' => 1,
            'kind' => AiMissionCardKind::Finding,
            'claim' => fake()->sentence(10),
            'source' => 'check_room_availability',
            'confidence' => AiMissionConfidence::Medium,
            'unsourced' => false,
        ];
    }
}
