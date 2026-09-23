<?php

namespace Database\Factories;

use App\Enums\PrintType;
use App\Models\AdmissionTicket;
use App\Models\PrintLog;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PrintLog>
 */
class PrintLogFactory extends Factory
{
    public function definition(): array
    {
        return [
            'admission_ticket_id' => AdmissionTicket::factory(),
            'print_type' => PrintType::Original,
            'printed_at' => now(),
        ];
    }
}
