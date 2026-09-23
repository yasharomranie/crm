<?php

namespace Database\Factories;

use App\Enums\GateScanResult;
use App\Models\Gate;
use App\Models\GateOfflineScanSync;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<GateOfflineScanSync>
 */
class GateOfflineScanSyncFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'gate_id' => Gate::factory(),
            'client_scan_id' => (string) Str::uuid(),
            'result' => GateScanResult::Success,
            'local_result' => GateScanResult::Success,
            'scanned_at' => now(),
        ];
    }
}
