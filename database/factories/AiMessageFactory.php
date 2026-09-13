<?php

namespace Database\Factories;

use App\Enums\AiMessageRole;
use App\Models\AiConversation;
use App\Models\AiMessage;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AiMessage>
 */
class AiMessageFactory extends Factory
{
    protected $model = AiMessage::class;

    public function definition(): array
    {
        return [
            'ai_conversation_id' => AiConversation::factory(),
            'role' => AiMessageRole::User,
            'content' => fake()->sentence(),
        ];
    }
}
