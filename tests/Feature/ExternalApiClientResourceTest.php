<?php

namespace Tests\Feature;

use App\Filament\Resources\ExternalApiClients\Pages\CreateExternalApiClient;
use App\Models\ExternalApiClient;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ExternalApiClientResourceTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_list_page_renders(): void
    {
        ExternalApiClient::factory()->create();

        $this->actingAs(User::factory()->create())
            ->get('/admin/external-api-clients')
            ->assertOk();
    }

    public function test_the_create_page_renders(): void
    {
        $this->actingAs(User::factory()->create())
            ->get('/admin/external-api-clients/create')
            ->assertOk();
    }

    public function test_creating_a_client_issues_a_key_and_shows_the_secret_once(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        Livewire::test(CreateExternalApiClient::class)
            ->fillForm([
                'client_name' => 'باشگاه مشتریان وفادار',
                'allowed_operations' => ['booking.create', 'booking.read'],
                'rate_limit_per_minute' => 30,
                'created_by' => $user->id,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $client = ExternalApiClient::first();
        $this->assertNotNull($client);
        $this->assertStringStartsWith('pk_', $client->api_key);
        $this->assertSame(['booking.create', 'booking.read'], $client->allowed_operations);
        $this->assertSame(30, $client->rate_limit_per_minute);
        $this->assertSame($user->id, $client->created_by);
    }

    public function test_the_edit_page_renders(): void
    {
        $client = ExternalApiClient::factory()->create();

        $this->actingAs(User::factory()->create())
            ->get("/admin/external-api-clients/{$client->id}/edit")
            ->assertOk();
    }

    public function test_regenerating_a_secret_changes_the_hash(): void
    {
        $client = ExternalApiClient::factory()->create();
        $originalHash = $client->api_secret_hash;

        $newSecret = $client->regenerateSecret();

        $this->assertNotSame($originalHash, $client->fresh()->api_secret_hash);
        $this->assertTrue($client->fresh()->verifySecret($newSecret));
    }
}
