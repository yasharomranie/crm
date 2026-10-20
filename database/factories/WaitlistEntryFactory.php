<?php

namespace Database\Factories;

use App\Models\Guest;
use App\Models\WaitlistEntry;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WaitlistEntry>
 */
class WaitlistEntryFactory extends Factory
{
    protected $model = WaitlistEntry::class;

    public function definition(): array
    {
        $checkIn = fake()->dateTimeBetween('+10 days', '+60 days');

        return [
            'guest_id' => Guest::factory(),
            'check_in_date' => $checkIn,
            'check_out_date' => (clone $checkIn)->modify('+2 days'),
            'adults_count' => 2,
            'children_count' => 0,
            'status' => WaitlistEntry::STATUS_WAITING,
        ];
    }
}
