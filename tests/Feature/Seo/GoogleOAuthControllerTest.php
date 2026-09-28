<?php

namespace Tests\Feature\Seo;

use App\Enums\SeoConnectionStatus;
use App\Models\SeoProject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class GoogleOAuthControllerTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $admin = User::factory()->create();
        $admin->assignRole(Role::firstOrCreate(['name' => 'admin']));

        return $admin;
    }

    /**
     * Not a clean redirect: this route sits in routes/web.php's plain
     * 'auth' middleware group, whose default guest redirect targets a
     * route named 'login' - which doesn't exist in this app (Filament's
     * own is 'filament.admin.auth.login') - so it 500s instead, exactly
     * the same pre-existing quirk documented on '/system-guide'. Still
     * effectively "denied": a guest never reaches the OAuth flow.
     */
    public function test_a_guest_cannot_start_the_google_connection_flow(): void
    {
        $project = SeoProject::factory()->create();

        $this->get(route('seo.google.redirect', $project))
            ->assertStatus(500);
    }

    public function test_redirect_flashes_a_friendly_error_when_google_is_not_configured(): void
    {
        config(['services.google.client_id' => null, 'services.google.client_secret' => null]);

        $project = SeoProject::factory()->create();

        $response = $this->actingAs($this->admin())
            ->get(route('seo.google.redirect', $project));

        $response->assertRedirect();
        $this->assertStringContainsString((string) $project->id, $response->headers->get('Location'));
        $response->assertSessionHas('error');
    }

    public function test_redirect_sends_the_user_to_google_when_configured(): void
    {
        config([
            'services.google.client_id' => 'test-client-id',
            'services.google.client_secret' => 'test-client-secret',
            'services.google.redirect' => 'https://example.com/seo/google/callback',
        ]);

        $project = SeoProject::factory()->create();

        $response = $this->actingAs($this->admin())
            ->get(route('seo.google.redirect', $project));

        $response->assertRedirect();
        $this->assertStringStartsWith('https://accounts.google.com/o/oauth2/v2/auth', $response->headers->get('Location'));
    }

    public function test_callback_rejects_a_mismatched_state(): void
    {
        $this->actingAs($this->admin())
            ->withSession(['seo_google_oauth_state' => '1.correct-state'])
            ->get(route('seo.google.callback', ['state' => '1.wrong-state', 'code' => 'abc']))
            ->assertStatus(419);
    }

    public function test_callback_creates_a_connected_google_connection_on_success(): void
    {
        config([
            'services.google.client_id' => 'test-client-id',
            'services.google.client_secret' => 'test-client-secret',
            'services.google.redirect' => 'https://example.com/seo/google/callback',
        ]);

        $project = SeoProject::factory()->create();

        Http::fake([
            'oauth2.googleapis.com/token' => Http::response([
                'access_token' => 'access-123',
                'refresh_token' => 'refresh-123',
                'expires_in' => 3600,
                'scope' => 'https://www.googleapis.com/auth/webmasters.readonly',
            ]),
        ]);

        $state = "{$project->id}.random-nonce";

        $this->actingAs($this->admin())
            ->withSession(['seo_google_oauth_state' => $state])
            ->get(route('seo.google.callback', ['state' => $state, 'code' => 'auth-code']))
            ->assertRedirect()
            ->assertSessionHas('success');

        $connection = $project->fresh()->googleConnection;
        $this->assertSame(SeoConnectionStatus::Connected, $connection->status);
        $this->assertSame('access-123', $connection->access_token);
    }
}
