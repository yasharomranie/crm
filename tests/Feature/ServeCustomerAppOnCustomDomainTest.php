<?php

namespace Tests\Feature;

use App\Models\WebAppDomainSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class ServeCustomerAppOnCustomDomainTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Laravel's TestCase::get() always rebuilds the request URI via the
     * url() helper (see MakesHttpRequests::prepareUrlForRequest()), and
     * Symfony\Component\HttpFoundation\Request::create() then derives
     * HTTP_HOST from THAT URI - so a plain 'Host' header override is
     * silently discarded, and even changing config('app.url') does
     * nothing on its own because UrlGenerator memoizes its root the
     * first time it's read. URL::forceRootUrl() is the one call that
     * both overrides it AND busts that cache, so $request->getHost()
     * can actually resolve to something other than the app's normal
     * test host - which is the only way to exercise Host-based
     * dispatch here at all.
     */
    private function withAppUrl(string $url, callable $callback): mixed
    {
        URL::forceRootUrl($url);

        try {
            return $callback();
        } finally {
            URL::forceRootUrl(null);
        }
    }

    public function test_root_shows_the_customer_app_when_the_host_matches_an_enabled_and_verified_domain(): void
    {
        WebAppDomainSetting::current()->update([
            'domain' => 'app.example.com',
            'is_enabled' => true,
            'dns_verified_at' => now(),
        ]);

        $response = $this->withAppUrl('https://app.example.com', fn () => $this->get('/'));

        $response->assertOk();
        $response->assertViewIs('customer-app');
    }

    public function test_root_still_shows_the_default_page_on_the_main_host_even_when_a_domain_is_connected(): void
    {
        WebAppDomainSetting::current()->update([
            'domain' => 'app.example.com',
            'is_enabled' => true,
            'dns_verified_at' => now(),
        ]);

        $response = $this->get('/');

        $response->assertOk();
        $response->assertViewIs('welcome');
    }

    public function test_root_is_not_hijacked_when_the_domain_is_enabled_but_never_dns_verified(): void
    {
        WebAppDomainSetting::current()->update([
            'domain' => 'app.example.com',
            'is_enabled' => true,
            'dns_verified_at' => null,
        ]);

        $response = $this->withAppUrl('https://app.example.com', fn () => $this->get('/'));

        $response->assertViewIs('welcome');
    }

    public function test_root_is_not_hijacked_when_the_toggle_is_disabled_even_if_previously_verified(): void
    {
        WebAppDomainSetting::current()->update([
            'domain' => 'app.example.com',
            'is_enabled' => false,
            'dns_verified_at' => now(),
        ]);

        $response = $this->withAppUrl('https://app.example.com', fn () => $this->get('/'));

        $response->assertViewIs('welcome');
    }

    public function test_the_apps_own_app_path_keeps_working_regardless_of_host(): void
    {
        WebAppDomainSetting::current()->update([
            'domain' => 'app.example.com',
            'is_enabled' => true,
            'dns_verified_at' => now(),
        ]);

        $response = $this->withAppUrl('https://app.example.com', fn () => $this->get('/app'));

        $response->assertOk();
        $response->assertViewIs('customer-app');
    }
}
