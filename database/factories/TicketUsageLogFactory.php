<?php

namespace Database\Factories;

use App\Enums\GateScanResult;
use App\Models\TicketUsageLog;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TicketUsageLog>
 */
class TicketUsageLogFactory extends Factory
{
    public function definition(): array
    {
        return [
            'result' => GateScanResult::Success,
            'scanned_at' => now(),
        ];
    }
}
