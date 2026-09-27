<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApiDocsTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_guest_is_redirected_to_the_admin_login_page(): void
    {
        $this->get(route('api-docs'))
            ->assertRedirect(route('filament.admin.auth.login'));
    }

    public function test_an_authenticated_user_can_view_the_docs(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('api-docs'))
            ->assertOk()
            ->assertSee('REST API سامانه‌ی FarsiWP')
            ->assertSee('/api/v1/external')
            ->assertSee('X-Webhook-Signature');
    }
}
