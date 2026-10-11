<?php

namespace Database\Factories;

use App\Models\WhatsAppConversation;
use App\Models\WhatsAppMessage;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WhatsAppMessage>
 */
class WhatsAppMessageFactory extends Factory
{
    protected $model = WhatsAppMessage::class;

    public function definition(): array
    {
        return [
            'whats_app_conversation_id' => WhatsAppConversation::factory(),
            'direction' => 'inbound',
            'content' => fake()->sentence(),
        ];
    }
}
