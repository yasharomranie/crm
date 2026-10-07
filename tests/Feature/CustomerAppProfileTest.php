<?php

namespace Tests\Feature;

use App\Models\Guest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerAppProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_guest_cannot_view_the_profile_page_without_logging_in(): void
    {
        $this->get(route('customer-app.profile'))
            ->assertRedirect(route('customer-app.auth.login'));
    }

    public function test_the_profile_page_shows_the_guests_own_info(): void
    {
        $guest = Guest::factory()->create([
            'name' => 'رضا محمدی',
            'mobile' => '09120000051',
            'email' => 'reza@example.test',
            'wallet_balance' => 75000,
        ]);

        $response = $this->actingAs($guest, 'customer')->get(route('customer-app.profile'));

        $response->assertOk();
        $response->assertSee('رضا محمدی');
        $response->assertSee('09120000051');
        $response->assertSee('reza@example.test');
        $response->assertSee(number_format(75000));
    }

    public function test_the_bottom_nav_auth_button_links_to_the_login_page_when_signed_out(): void
    {
        $this->get(route('customer-app'))
            ->assertOk()
            ->assertSee(route('customer-app.auth.login'), false);
    }

    public function test_the_bottom_nav_auth_button_links_to_the_profile_page_when_signed_in(): void
    {
        $guest = Guest::factory()->create();

        $this->actingAs($guest, 'customer')
            ->get(route('customer-app.wallet'))
            ->assertOk()
            ->assertSee(route('customer-app.profile'), false);
    }
}
