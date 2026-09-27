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
}
