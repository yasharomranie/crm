<?php

namespace Database\Factories;

use App\Enums\TicketTemplateStatus;
use App\Models\TicketTemplate;
use App\Models\TicketTemplateVersion;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TicketTemplateVersion>
 */
class TicketTemplateVersionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'ticket_template_id' => TicketTemplate::factory(),
            'status' => TicketTemplateStatus::Active,
            'width_mm' => 100,
            'height_mm' => 150,
        ];
    }
}
