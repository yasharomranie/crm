<?php

namespace Database\Factories;

use App\Models\ExternalApiClient;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<ExternalApiClient>
 */
class ExternalApiClientFactory extends Factory
{
    public function definition(): array
    {
        return [
            'client_name' => fake()->company(),
            'api_key' => 'pk_'.Str::random(32),
            'api_secret_hash' => Hash::make('test-secret'),
            'allowed_operations' => [
                'product.read', 'ticket_variant.read', 'ticket.issue', 'ticket.read', 'ticket.reprint',
                'ticket.refund', 'discount_code.validate', 'gate.scan',
                'room.read', 'booking.create', 'booking.read',
                'deal.create', 'deal.read',
                'form_submission.create',
                'document.upload', 'document.read',
                'webhook.manage',
            ],
            'is_active' => true,
            'rate_limit_per_minute' => 60,
        ];
    }
}
