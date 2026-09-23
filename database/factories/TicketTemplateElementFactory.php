<?php

namespace Database\Factories;

use App\Models\TicketTemplateElement;
use App\Models\TicketTemplateVersion;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TicketTemplateElement>
 */
class TicketTemplateElementFactory extends Factory
{
    public function definition(): array
    {
        return [
            'ticket_template_version_id' => TicketTemplateVersion::factory(),
            'type' => 'text',
            'content' => fake()->word(),
            'x' => 0,
            'y' => 0,
            'width' => 100,
            'height' => 30,
        ];
    }
}
