<?php

namespace Tests\Feature;

use App\Models\CustomerAppAuthSetting;
use App\Models\CustomerAppGoogleSetting;
use App\Models\Guest;
use App\Models\GuestOtpCode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class CustomerAppAuthTest extends TestCase
{
    use RefreshDatabase;

    // ---------- OTP ----------

    public function test_requesting_an_otp_creates_a_code_and_redirects_to_the_code_step(): void
    {
        $response = $this->post(route('customer-app.auth.otp.request'), ['mobile' => '09120000011']);

        $response->assertRedirect(route('customer-app.auth.login', ['step' => 'otp', 'mobile' => '09120000011']));
        $this->assertDatabaseHas('guest_otp_codes', ['mobile' => '09120000011']);
    }

    public function test_verifying_the_correct_otp_logs_in_and_creates_a_guest(): void
    {
        $this->post(route('customer-app.auth.otp.request'), ['mobile' => '09120000012']);
        $code = GuestOtpCode::where('mobile', '09120000012')->latest('id')->first();

        $response = $this->post(route('customer-app.auth.otp.verify'), [
            'mobile' => '09120000012',
            'code' => $code->code,
        ]);

        $response->assertRedirect(route('customer-app.profile'));
        $this->assertAuthenticated('customer');

        $guest = Guest::where('mobile', '09120000012')->first();
        $this->assertNotNull($guest);
        $this->assertNotNull($guest->mobile_verified_at);
    }

    public function test_verifying_an_existing_guests_mobile_logs_into_their_existing_account(): void
    {
        $guest = Guest::factory()->create(['mobile' => '09120000013']);

        $this->post(route('customer-app.auth.otp.request'), ['mobile' => '09120000013']);
        $code = GuestOtpCode::where('mobile', '09120000013')->latest('id')->first();

        $this->post(route('customer-app.auth.otp.verify'), [
            'mobile' => '09120000013',
            'code' => $code->code,
        ]);

        $this->assertAuthenticatedAs($guest, 'customer');
        $this->assertSame(1, Guest::where('mobile', '09120000013')->count());
    }

    public function test_verifying_a_wrong_code_fails_without_logging_in(): void
    {
        $this->post(route('customer-app.auth.otp.request'), ['mobile' => '09120000014']);

        $response = $this->post(route('customer-app.auth.otp.verify'), [
            'mobile' => '09120000014',
            'code' => '000000',
        ]);

        $response->assertSessionHasErrors('code');
        $this->assertGuest('customer');
    }

    public function test_verifying_an_expired_code_fails(): void
    {
        GuestOtpCode::create([
            'mobile' => '09120000015',
            'code' => '111111',
            'expires_at' => now()->subMinute(),
        ]);

        $response = $this->post(route('customer-app.auth.otp.verify'), [
            'mobile' => '09120000015',
            'code' => '111111',
        ]);

        $response->assertSessionHasErrors('code');
        $this->assertGuest('customer');
    }

    public function test_a_code_is_single_use(): void
    {
        $this->post(route('customer-app.auth.otp.request'), ['mobile' => '09120000016']);
        $code = GuestOtpCode::where('mobile', '09120000016')->latest('id')->first();

        $this->post(route('customer-app.auth.otp.verify'), ['mobile' => '09120000016', 'code' => $code->code]);
        $this->post(route('customer-app.auth.logout'));

        $response = $this->post(route('customer-app.auth.otp.verify'), ['mobile' => '09120000016', 'code' => $code->code]);

        $response->assertSessionHasErrors('code');
        $this->assertGuest('customer');
    }

    public function test_requesting_a_new_code_invalidates_the_previous_one(): void
    {
        $this->post(route('customer-app.auth.otp.request'), ['mobile' => '09120000017']);
        $firstCode = GuestOtpCode::where('mobile', '09120000017')->latest('id')->first();

        $this->post(route('customer-app.auth.otp.request'), ['mobile' => '09120000017']);

        $response = $this->post(route('customer-app.auth.otp.verify'), [
            'mobile' => '09120000017',
            'code' => $firstCode->code,
        ]);

        $response->assertSessionHasErrors('code');
        $this->assertGuest('customer');
    }

    // ---------- Password register/login ----------

    public function test_registering_with_a_password_creates_a_guest_and_logs_in(): void
    {
        $response = $this->post(route('customer-app.auth.register'), [
            'name' => 'سارا احمدی',
            'mobile' => '09120000021',
            'password' => 'a-strong-password',
            'password_confirmation' => 'a-strong-password',
        ]);

        $response->assertRedirect(route('customer-app.profile'));
        $this->assertAuthenticated('customer');

        $guest = Guest::where('mobile', '09120000021')->first();
        $this->assertSame('سارا احمدی', $guest->name);
    }

    public function test_registering_with_an_already_taken_mobile_fails(): void
    {
        Guest::factory()->create(['mobile' => '09120000022']);

        $response = $this->post(route('customer-app.auth.register'), [
            'name' => 'کاربر دوم',
            'mobile' => '09120000022',
            'password' => 'a-strong-password',
            'password_confirmation' => 'a-strong-password',
        ]);

        $response->assertSessionHasErrors('mobile');
        $this->assertGuest('customer');
    }

    public function test_logging_in_with_mobile_and_password_succeeds(): void
    {
        Guest::factory()->create(['mobile' => '09120000023', 'password' => 'correct-password']);

        $response = $this->post(route('customer-app.auth.login.attempt'), [
            'login' => '09120000023',
            'password' => 'correct-password',
        ]);

        $response->assertRedirect(route('customer-app.profile'));
        $this->assertAuthenticated('customer');
    }

    public function test_logging_in_with_email_and_password_succeeds(): void
    {
        Guest::factory()->create(['email' => 'sara@example.test', 'password' => 'correct-password']);

        $response = $this->post(route('customer-app.auth.login.attempt'), [
            'login' => 'sara@example.test',
            'password' => 'correct-password',
        ]);

        $response->assertRedirect(route('customer-app.profile'));
        $this->assertAuthenticated('customer');
    }

    public function test_logging_in_with_a_wrong_password_fails(): void
    {
        Guest::factory()->create(['mobile' => '09120000024', 'password' => 'correct-password']);

        $response = $this->post(route('customer-app.auth.login.attempt'), [
            'login' => '09120000024',
            'password' => 'wrong-password',
        ]);

        $response->assertSessionHasErrors('login');
        $this->assertGuest('customer');
    }

    // ---------- Google ----------

    public function test_google_redirect_fails_gracefully_when_not_configured(): void
    {
        $response = $this->get(route('customer-app.auth.google.redirect'));

        $response->assertRedirect(route('customer-app.auth.login'));
        $response->assertSessionHasErrors('google');
    }

    public function test_google_redirect_sends_the_user_to_google_when_configured(): void
    {
        CustomerAppGoogleSetting::current()->update(['client_id' => 'id', 'client_secret' => 'secret']);

        $response = $this->get(route('customer-app.auth.google.redirect'));

        $response->assertRedirect();
        $this->assertStringStartsWith('https://accounts.google.com/o/oauth2/v2/auth', $response->headers->get('Location'));
    }

    public function test_google_callback_rejects_a_mismatched_state(): void
    {
        CustomerAppGoogleSetting::current()->update(['client_id' => 'id', 'client_secret' => 'secret']);

        $response = $this->withSession(['customer_google_oauth_state' => 'correct-state'])
            ->get(route('customer-app.auth.google.callback', ['state' => 'wrong-state', 'code' => 'abc']));

        $response->assertRedirect(route('customer-app.auth.login'));
        $response->assertSessionHasErrors('google');
        $this->assertGuest('customer');
    }

    public function test_google_callback_creates_a_new_guest_and_logs_in(): void
    {
        CustomerAppGoogleSetting::current()->update(['client_id' => 'id', 'client_secret' => 'secret']);

        Http::fake([
            'oauth2.googleapis.com/token' => Http::response(['access_token' => 'access-123']),
            'www.googleapis.com/oauth2/v3/userinfo' => Http::response([
                'sub' => 'google-sub-1',
                'email' => 'new-guest@example.test',
                'name' => 'مهمان گوگلی',
            ]),
        ]);

        $response = $this->withSession(['customer_google_oauth_state' => 'state-1'])
            ->get(route('customer-app.auth.google.callback', ['state' => 'state-1', 'code' => 'auth-code']));

        $response->assertRedirect(route('customer-app.profile'));
        $this->assertAuthenticated('customer');

        $guest = Guest::where('google_id', 'google-sub-1')->first();
        $this->assertNotNull($guest);
        $this->assertSame('new-guest@example.test', $guest->email);
    }

    public function test_google_callback_links_an_existing_guest_found_by_email(): void
    {
        CustomerAppGoogleSetting::current()->update(['client_id' => 'id', 'client_secret' => 'secret']);

        $guest = Guest::factory()->create(['email' => 'existing@example.test', 'google_id' => null]);

        Http::fake([
            'oauth2.googleapis.com/token' => Http::response(['access_token' => 'access-123']),
            'www.googleapis.com/oauth2/v3/userinfo' => Http::response([
                'sub' => 'google-sub-2',
                'email' => 'existing@example.test',
                'name' => 'نام گوگل',
            ]),
        ]);

        $this->withSession(['customer_google_oauth_state' => 'state-2'])
            ->get(route('customer-app.auth.google.callback', ['state' => 'state-2', 'code' => 'auth-code']));

        $this->assertAuthenticatedAs($guest->fresh(), 'customer');
        $this->assertSame('google-sub-2', $guest->fresh()->google_id);
        $this->assertSame(1, Guest::where('email', 'existing@example.test')->count());
    }

    // ---------- Logout + the public home page ----------

    public function test_logout_clears_the_customer_session(): void
    {
        $guest = Guest::factory()->create();
        $this->actingAs($guest, 'customer');

        $response = $this->post(route('customer-app.auth.logout'));

        $response->assertRedirect(route('customer-app'));
        $this->assertGuest('customer');
    }

    public function test_the_home_page_shows_a_login_link_when_signed_out(): void
    {
        $this->get(route('customer-app'))
            ->assertOk()
            ->assertSee(route('customer-app.auth.login'), false);
    }

    public function test_the_home_page_greets_a_signed_in_guest(): void
    {
        $guest = Guest::factory()->create(['name' => 'رضا محمدی']);

        $this->actingAs($guest, 'customer')
            ->get(route('customer-app'))
            ->assertOk()
            ->assertSee('رضا محمدی');
    }

    // ---------- Per-method enable/disable toggles ----------

    public function test_a_disabled_otp_method_rejects_a_request_even_when_posted_directly(): void
    {
        CustomerAppAuthSetting::current()->update(['otp_enabled' => false]);

        $this->post(route('customer-app.auth.otp.request'), ['mobile' => '09120000041'])
            ->assertForbidden();
    }

    public function test_a_disabled_password_method_rejects_login_even_when_posted_directly(): void
    {
        CustomerAppAuthSetting::current()->update(['password_enabled' => false]);
        Guest::factory()->create(['mobile' => '09120000042', 'password' => 'correct-password']);

        $this->post(route('customer-app.auth.login.attempt'), [
            'login' => '09120000042',
            'password' => 'correct-password',
        ])->assertForbidden();
    }

    public function test_a_disabled_password_method_rejects_registration_even_when_posted_directly(): void
    {
        CustomerAppAuthSetting::current()->update(['password_enabled' => false]);

        $this->post(route('customer-app.auth.register'), [
            'name' => 'کاربر تست',
            'mobile' => '09120000043',
            'password' => 'a-strong-password',
            'password_confirmation' => 'a-strong-password',
        ])->assertForbidden();
    }

    public function test_a_disabled_google_method_bounces_back_to_login_with_an_error(): void
    {
        CustomerAppGoogleSetting::current()->update(['client_id' => 'id', 'client_secret' => 'secret']);
        CustomerAppAuthSetting::current()->update(['google_enabled' => false]);

        $response = $this->get(route('customer-app.auth.google.redirect'));

        $response->assertRedirect(route('customer-app.auth.login'));
        $response->assertSessionHasErrors('google');
    }

    public function test_the_login_page_hides_disabled_methods(): void
    {
        CustomerAppAuthSetting::current()->update(['otp_enabled' => false]);

        $this->get(route('customer-app.auth.login'))
            ->assertOk()
            ->assertDontSee('ارسال کد ورود');
    }
}
