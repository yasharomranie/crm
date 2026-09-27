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

    public function test_the_docs_include_the_quick_start_guide_and_full_parameter_tables(): void
    {
        $response = $this->actingAs(User::factory()->create())
            ->get(route('api-docs'));

        $response->assertOk()
            ->assertSee('راهنمای شروع سریع')
            ->assertSee('uuidgen')
            ->assertSee('مرجع کامل مجوزها')
            ->assertSee('discount_code.validate')
            ->assertSee('guest.mobile')
            ->assertSee('RATE_LIMITED');
    }
}
