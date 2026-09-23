<?php

namespace Database\Factories;

use App\Models\AdmissionTicket;
use App\Models\Gate;
use App\Models\TicketUsage;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TicketUsage>
 */
class TicketUsageFactory extends Factory
{
    public function definition(): array
    {
        return [
            'admission_ticket_id' => AdmissionTicket::factory(),
            'gate_id' => Gate::factory(),
            'usage_number' => 1,
        ];
    }
}
