<?php

namespace Database\Factories;

use App\Enums\WebhookEventType;
use App\Models\ExternalApiClient;
use App\Models\WebhookSubscription;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<WebhookSubscription>
 */
class WebhookSubscriptionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'external_api_client_id' => ExternalApiClient::factory(),
            'event_type' => WebhookEventType::TicketScanned,
            'target_url' => 'https://example.test/webhooks/'.Str::random(8),
            'signing_secret' => Str::random(40),
            'is_active' => true,
        ];
    }
}
