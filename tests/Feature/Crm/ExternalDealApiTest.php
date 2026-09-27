<?php

namespace Tests\Feature\Crm;

use App\Enums\DealStage;
use App\Models\Deal;
use App\Models\ExternalApiClient;
use App\Models\Guest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExternalDealApiTest extends TestCase
{
    use RefreshDatabase;

    private function headers(ExternalApiClient $client, string $secret = 'test-secret'): array
    {
        return ['X-API-Key' => $client->api_key, 'X-API-Secret' => $secret];
    }

    public function test_a_lead_can_be_created_with_a_new_guest(): void
    {
        $client = ExternalApiClient::factory()->create();

        $response = $this->postJson('/api/v1/external/deals', [
            'title' => 'درخواست تور از سایت',
            'value' => 5000000,
            'guest' => ['name' => 'نیما کریمی', 'mobile' => '09129998877'],
        ], [...$this->headers($client), 'Idempotency-Key' => 'deal-1']);

        $response->assertStatus(201);
        $this->assertSame(DealStage::NewLead, Deal::first()->stage);
        $this->assertSame(1, Guest::where('mobile', '09129998877')->count());
        $this->assertSame($client->id, Deal::first()->external_api_client_id);
    }

    public function test_a_lead_can_be_created_without_a_guest(): void
    {
        $client = ExternalApiClient::factory()->create();

        $this->postJson('/api/v1/external/deals', [
            'title' => 'تماس تلفنی بدون شماره',
        ], [...$this->headers($client), 'Idempotency-Key' => 'deal-2'])->assertStatus(201);

        $this->assertNull(Deal::first()->guest_id);
    }

    public function test_a_client_can_read_back_its_own_deal(): void
    {
        $client = ExternalApiClient::factory()->create();

        $create = $this->postJson('/api/v1/external/deals', [
            'title' => 'سرنخ برای پیگیری',
        ], [...$this->headers($client), 'Idempotency-Key' => 'deal-3']);

        $dealId = $create->json('deal_id');

        $this->getJson("/api/v1/external/deals/{$dealId}", $this->headers($client))
            ->assertOk()
            ->assertJsonPath('stage', 'new_lead');
    }

    public function test_a_client_cannot_read_a_deal_created_by_a_different_client(): void
    {
        $owner = ExternalApiClient::factory()->create();
        $stranger = ExternalApiClient::factory()->create();

        $create = $this->postJson('/api/v1/external/deals', [
            'title' => 'سرنخ محرمانه',
        ], [...$this->headers($owner), 'Idempotency-Key' => 'deal-4']);

        $this->getJson('/api/v1/external/deals/'.$create->json('deal_id'), $this->headers($stranger))
            ->assertStatus(404);
    }

    public function test_a_deal_created_from_the_admin_panel_is_not_readable_through_the_api(): void
    {
        $deal = Deal::create(['title' => 'سرنخ داخلی']);
        $client = ExternalApiClient::factory()->create();

        $this->getJson("/api/v1/external/deals/{$deal->id}", $this->headers($client))
            ->assertStatus(404);
    }

    public function test_a_client_without_deal_create_permission_is_forbidden(): void
    {
        $client = ExternalApiClient::factory()->create(['allowed_operations' => ['deal.read']]);

        $response = $this->postJson('/api/v1/external/deals', [
            'title' => 'درخواست تور از سایت',
        ], [...$this->headers($client), 'Idempotency-Key' => 'deal-forbidden']);

        $response->assertStatus(403)->assertJsonPath('error.code', 'FORBIDDEN');
        $this->assertSame(0, Deal::count());
    }

    public function test_a_client_without_deal_read_permission_is_forbidden(): void
    {
        $deal = Deal::create(['title' => 'سرنخ داخلی']);
        $client = ExternalApiClient::factory()->create(['allowed_operations' => ['deal.create']]);

        $this->getJson("/api/v1/external/deals/{$deal->id}", $this->headers($client))
            ->assertStatus(403)
            ->assertJsonPath('error.code', 'FORBIDDEN');
    }
}
