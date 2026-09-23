<?php

namespace Database\Factories;

use App\Models\AdmissionTicket;
use App\Models\QrToken;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<QrToken>
 */
class QrTokenFactory extends Factory
{
    public function definition(): array
    {
        return [
            'admission_ticket_id' => AdmissionTicket::factory(),
            'token' => QrToken::generateToken(),
        ];
    }
}
