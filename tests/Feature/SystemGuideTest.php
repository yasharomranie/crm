<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SystemGuideTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_guest_is_redirected_to_the_admin_login_page(): void
    {
        $this->get(route('system-guide'))
            ->assertRedirect(route('filament.admin.auth.login'));
    }

    public function test_an_authenticated_user_can_view_the_guide(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('system-guide'))
            ->assertOk()
            ->assertSee('راهنمای سامانه FarsiWP');
    }

    public function test_the_guide_includes_the_rest_api_chapter_with_full_details(): void
    {
        $response = $this->actingAs(User::factory()->create())
            ->get(route('system-guide'));

        $response->assertOk()
            ->assertSee('REST API برای سامانه‌های بیرونی')
            ->assertSee('/api/v1/external')
            ->assertSee('uuidgen')
            ->assertSee('X-Webhook-Signature')
            ->assertSee('discount_code.validate')
            ->assertSee('guest.mobile')
            ->assertSee('RATE_LIMITED');
    }

    public function test_the_old_api_docs_url_redirects_into_the_guide(): void
    {
        $this->get('/api-docs')->assertRedirect('/system-guide#ch-13');
    }
}
