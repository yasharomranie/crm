<?php

namespace Database\Factories;

use App\Models\AiAssistantProfile;
use App\Models\WhatsAppConversation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WhatsAppConversation>
 */
class WhatsAppConversationFactory extends Factory
{
    protected $model = WhatsAppConversation::class;

    public function definition(): array
    {
        return [
            'ai_assistant_profile_id' => AiAssistantProfile::factory(),
            'wa_id' => fake()->numerify('989#########'),
            'contact_name' => fake()->name(),
        ];
    }
}
