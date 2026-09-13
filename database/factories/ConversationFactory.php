<?php

namespace Database\Factories;

use App\Enums\ConversationType;
use App\Models\Conversation;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Conversation>
 */
class ConversationFactory extends Factory
{
    protected $model = Conversation::class;

    public function definition(): array
    {
        return [
            'type' => ConversationType::Direct,
            'created_by' => User::factory(),
        ];
    }

    public function group(): self
    {
        return $this->state([
            'type' => ConversationType::Group,
            'name' => fake()->words(2, true),
        ]);
    }
}
