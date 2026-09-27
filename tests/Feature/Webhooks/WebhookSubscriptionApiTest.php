<?php

namespace Tests\Feature\Webhooks;

use App\Enums\WebhookEventType;
use App\Models\ExternalApiClient;
use App\Models\WebhookSubscription;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WebhookSubscriptionApiTest extends TestCase
{
    use RefreshDatabase;

    private function headers(ExternalApiClient $client, string $secret = 'test-secret'): array
    {
        return ['X-API-Key' => $client->api_key, 'X-API-Secret' => $secret];
    }

    public function test_a_client_can_register_a_webhook_subscription(): void
    {
        $client = ExternalApiClient::factory()->create();

        $response = $this->postJson('/api/v1/external/webhooks', [
            'event_type' => 'booking.created',
            'target_url' => 'https://example.com/hooks/bookings',
        ], $this->headers($client));

        $response->assertStatus(201);
        $this->assertNotEmpty($response->json('signing_secret'));
        $this->assertSame(1, WebhookSubscription::count());
        $subscription = WebhookSubscription::first();
        $this->assertSame($client->id, $subscription->external_api_client_id);
        $this->assertSame(WebhookEventType::BookingCreated, $subscription->event_type);
    }

    public function test_an_invalid_event_type_is_rejected(): void
    {
        $client = ExternalApiClient::factory()->create();

        $this->postJson('/api/v1/external/webhooks', [
            'event_type' => 'not_a_real_event',
            'target_url' => 'https://example.com/hooks',
        ], $this->headers($client))->assertStatus(422);
    }

    public function test_a_client_can_list_only_its_own_subscriptions(): void
    {
        $client = ExternalApiClient::factory()->create();
        $other = ExternalApiClient::factory()->create();
        WebhookSubscription::factory()->create(['external_api_client_id' => $client->id]);
        WebhookSubscription::factory()->create(['external_api_client_id' => $other->id]);

        $response = $this->getJson('/api/v1/external/webhooks', $this->headers($client));

        $response->assertOk();
        $this->assertCount(1, $response->json('data'));
    }

    public function test_the_signing_secret_is_never_returned_by_the_list_endpoint(): void
    {
        $client = ExternalApiClient::factory()->create();
        WebhookSubscription::factory()->create(['external_api_client_id' => $client->id]);

        $response = $this->getJson('/api/v1/external/webhooks', $this->headers($client));

        $response->assertOk();
        $this->assertArrayNotHasKey('signing_secret', $response->json('data')[0]);
    }

    public function test_a_client_can_delete_its_own_subscription(): void
    {
        $client = ExternalApiClient::factory()->create();
        $subscription = WebhookSubscription::factory()->create(['external_api_client_id' => $client->id]);

        $response = $this->deleteJson("/api/v1/external/webhooks/{$subscription->id}", [], $this->headers($client));

        $response->assertStatus(204);
        $this->assertSame(0, WebhookSubscription::count());
    }

    public function test_a_client_cannot_delete_another_clients_subscription(): void
    {
        $owner = ExternalApiClient::factory()->create();
        $stranger = ExternalApiClient::factory()->create();
        $subscription = WebhookSubscription::factory()->create(['external_api_client_id' => $owner->id]);

        $response = $this->deleteJson("/api/v1/external/webhooks/{$subscription->id}", [], $this->headers($stranger));

        $response->assertStatus(404);
        $this->assertSame(1, WebhookSubscription::count());
    }

    public function test_a_client_without_webhook_manage_permission_is_forbidden(): void
    {
        $client = ExternalApiClient::factory()->create(['allowed_operations' => ['product.read']]);

        $this->postJson('/api/v1/external/webhooks', [
            'event_type' => 'booking.created',
            'target_url' => 'https://example.com/hooks',
        ], $this->headers($client))->assertStatus(403)->assertJsonPath('error.code', 'FORBIDDEN');

        $this->getJson('/api/v1/external/webhooks', $this->headers($client))
            ->assertStatus(403)->assertJsonPath('error.code', 'FORBIDDEN');

        $subscription = WebhookSubscription::factory()->create(['external_api_client_id' => $client->id]);
        $this->deleteJson("/api/v1/external/webhooks/{$subscription->id}", [], $this->headers($client))
            ->assertStatus(403)->assertJsonPath('error.code', 'FORBIDDEN');
    }
}
