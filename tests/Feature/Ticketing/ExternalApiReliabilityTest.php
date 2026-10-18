<?php

namespace Tests\Feature\Ticketing;

use App\Enums\DealStage;
use App\Enums\WebhookEventType;
use App\Filament\Resources\ExternalApiClients\Pages\EditExternalApiClient;
use App\Filament\Resources\ExternalApiClients\RelationManagers\WebhookSubscriptionsRelationManager;
use App\Jobs\DispatchWebhookJob;
use App\Models\Deal;
use App\Models\ExternalApiClient;
use App\Models\IdempotencyKey;
use App\Models\User;
use App\Models\WebhookSubscription;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ExternalApiReliabilityTest extends TestCase
{
    use RefreshDatabase;

    private function subscription(): WebhookSubscription
    {
        return WebhookSubscription::create([
            'external_api_client_id' => ExternalApiClient::factory()->create()->id,
            'event_type' => WebhookEventType::TicketScanned,
            'target_url' => 'https://receiver.test/hook',
            'signing_secret' => 'secret',
            'is_active' => true,
        ]);
    }

    public function test_an_error_answer_from_the_receiver_fails_the_job_so_it_is_retried(): void
    {
        Http::fake(['receiver.test/*' => Http::response('boom', 500)]);
        $subscription = $this->subscription();

        try {
            (new DispatchWebhookJob($subscription->id, WebhookEventType::TicketScanned, ['x' => 1]))->handle();
            $this->fail('Expected the 500 answer to fail the job.');
        } catch (RequestException) {
        }

        $fresh = $subscription->fresh();
        $this->assertNotNull($fresh->last_failed_at);
        $this->assertNotNull($fresh->last_error);
        $this->assertNull($fresh->last_triggered_at);
    }

    public function test_the_final_failure_is_counted_and_a_later_success_resets_it(): void
    {
        $subscription = $this->subscription();
        $job = new DispatchWebhookJob($subscription->id, WebhookEventType::TicketScanned, ['x' => 1]);

        $job->failed(null);
        $job->failed(null);
        $this->assertSame(2, $subscription->fresh()->consecutive_failures);

        Http::fake(['receiver.test/*' => Http::response('ok', 200)]);
        $job->handle();

        $fresh = $subscription->fresh();
        $this->assertSame(0, $fresh->consecutive_failures);
        $this->assertNull($fresh->last_error);
        $this->assertNotNull($fresh->last_triggered_at);
    }

    public function test_a_successful_delivery_is_signed(): void
    {
        Http::fake(['receiver.test/*' => Http::response('ok', 200)]);
        $subscription = $this->subscription();

        (new DispatchWebhookJob($subscription->id, WebhookEventType::TicketScanned, ['x' => 1]))->handle();

        Http::assertSent(fn ($request) => $request->hasHeader('X-Webhook-Signature') && $request->hasHeader('X-Webhook-Event', 'ticket.scanned'));
    }

    public function test_every_api_answer_tells_the_integration_how_much_rate_limit_is_left(): void
    {
        $client = ExternalApiClient::factory()->create(['rate_limit_per_minute' => 5]);
        $headers = ['X-API-Key' => $client->api_key, 'X-API-Secret' => 'test-secret'];

        $response = $this->getJson('/api/v1/external/products', $headers);

        $this->assertSame('5', $response->headers->get('X-RateLimit-Limit'));
        $this->assertNotNull($response->headers->get('X-RateLimit-Remaining'));
    }

    public function test_a_throttled_client_gets_retry_after(): void
    {
        $client = ExternalApiClient::factory()->create(['rate_limit_per_minute' => 1]);
        $headers = ['X-API-Key' => $client->api_key, 'X-API-Secret' => 'test-secret'];

        $this->getJson('/api/v1/external/products', $headers);
        $second = $this->getJson('/api/v1/external/products', $headers);

        $second->assertStatus(429);
        $this->assertNotNull($second->headers->get('Retry-After'));
    }

    public function test_a_deal_posted_twice_with_the_same_key_creates_one_deal(): void
    {
        $client = ExternalApiClient::factory()->create(['allowed_operations' => ['deal.create']]);
        $headers = ['X-API-Key' => $client->api_key, 'X-API-Secret' => 'test-secret', 'Idempotency-Key' => 'deal-1'];
        $body = ['title' => 'درخواست مشاوره'];

        $first = $this->postJson('/api/v1/external/deals', $body, $headers)->assertStatus(201);
        $second = $this->postJson('/api/v1/external/deals', $body, $headers)->assertStatus(201);

        $this->assertSame($first->json(), $second->json());
        $this->assertSame(1, Deal::count());
        $this->assertSame(1, IdempotencyKey::count());
    }

    public function test_a_losing_concurrent_request_leaves_no_duplicate_record_behind(): void
    {
        $client = ExternalApiClient::factory()->create(['allowed_operations' => ['deal.create']]);
        $hash = hash('sha256', json_encode(['client' => $client->id, 'title' => 'x']));

        // The winner of the race has already stored the key (and its deal)...
        Deal::create(['title' => 'x', 'stage' => DealStage::NewLead, 'external_api_client_id' => $client->id]);
        IdempotencyKey::create([
            'external_api_client_id' => $client->id, 'key' => 'k1', 'request_hash' => $hash,
            'response_status' => 201, 'response_body' => ['deal_id' => 1],
        ]);

        // ...so a request with the same key replays it and creates nothing new.
        $response = $this->postJson('/api/v1/external/deals', ['title' => 'x'], [
            'X-API-Key' => $client->api_key, 'X-API-Secret' => 'test-secret', 'Idempotency-Key' => 'k1',
        ]);

        $response->assertStatus(201);
        $this->assertSame(1, Deal::count());
    }

    public function test_an_admin_can_set_up_a_webhook_and_sees_its_failures(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole(Role::firstOrCreate(['name' => 'admin']));
        $client = ExternalApiClient::factory()->create();

        Livewire::actingAs($admin)
            ->test(WebhookSubscriptionsRelationManager::class, ['ownerRecord' => $client, 'pageClass' => EditExternalApiClient::class])
            ->mountTableAction('create')
            ->setTableActionData(['event_type' => 'ticket.scanned', 'target_url' => 'https://receiver.test/h', 'signing_secret' => 'abc', 'is_active' => true])
            ->callMountedTableAction()
            ->assertHasNoTableActionErrors();

        $subscription = $client->webhookSubscriptions()->sole();
        $subscription->update(['consecutive_failures' => 4, 'last_error' => 'HTTP 500']);

        Livewire::actingAs($admin)
            ->test(WebhookSubscriptionsRelationManager::class, ['ownerRecord' => $client, 'pageClass' => EditExternalApiClient::class])
            ->assertSee('HTTP 500');
    }
}
