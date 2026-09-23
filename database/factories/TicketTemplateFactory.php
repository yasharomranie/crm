<?php

namespace Database\Factories;

use App\Models\TicketTemplate;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TicketTemplate>
 */
class TicketTemplateFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->words(2, true).' Template',
        ];
    }
}
