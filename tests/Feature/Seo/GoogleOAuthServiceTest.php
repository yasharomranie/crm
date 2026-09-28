<?php

namespace Tests\Feature\Seo;

use App\Domain\Seo\GoogleOAuthService;
use App\Exceptions\SeoGoogleException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GoogleOAuthServiceTest extends TestCase
{
    use RefreshDatabase;

    private function configureGoogle(): void
    {
        config([
            'services.google.client_id' => 'test-client-id',
            'services.google.client_secret' => 'test-client-secret',
            'services.google.redirect' => 'https://example.com/seo/google/callback',
        ]);
    }

    public function test_it_refuses_to_build_an_authorization_url_when_not_configured(): void
    {
        config(['services.google.client_id' => null, 'services.google.client_secret' => null]);

        $this->expectException(SeoGoogleException::class);

        (new GoogleOAuthService)->buildAuthorizationUrl(['scope-a'], 'state-123');
    }

    public function test_it_builds_an_authorization_url_with_the_expected_parameters(): void
    {
        $this->configureGoogle();

        $url = (new GoogleOAuthService)->buildAuthorizationUrl(
            ['https://www.googleapis.com/auth/webmasters.readonly'],
            'state-123',
        );

        $this->assertStringStartsWith('https://accounts.google.com/o/oauth2/v2/auth?', $url);
        $this->assertStringContainsString('client_id=test-client-id', $url);
        $this->assertStringContainsString('state=state-123', $url);
        $this->assertStringContainsString('access_type=offline', $url);
        $this->assertStringContainsString('prompt=consent', $url);
        $this->assertStringContainsString(urlencode('https://www.googleapis.com/auth/webmasters.readonly'), $url);
    }

    public function test_it_exchanges_a_code_for_tokens(): void
    {
        $this->configureGoogle();

        Http::fake([
            'oauth2.googleapis.com/token' => Http::response([
                'access_token' => 'access-123',
                'refresh_token' => 'refresh-123',
                'expires_in' => 3600,
                'scope' => 'https://www.googleapis.com/auth/webmasters.readonly',
            ]),
        ]);

        $tokens = (new GoogleOAuthService)->exchangeCodeForToken('auth-code');

        $this->assertSame('access-123', $tokens['access_token']);
        $this->assertSame('refresh-123', $tokens['refresh_token']);

        Http::assertSent(fn ($request) => $request['grant_type'] === 'authorization_code'
            && $request['code'] === 'auth-code'
            && $request['client_id'] === 'test-client-id');
    }

    public function test_it_throws_with_googles_error_description_when_the_exchange_fails(): void
    {
        $this->configureGoogle();

        Http::fake([
            'oauth2.googleapis.com/token' => Http::response([
                'error' => 'invalid_grant',
                'error_description' => 'کد نامعتبر یا منقضی‌شده است.',
            ], 400),
        ]);

        $this->expectException(SeoGoogleException::class);
        $this->expectExceptionMessage('کد نامعتبر یا منقضی‌شده است.');

        (new GoogleOAuthService)->exchangeCodeForToken('bad-code');
    }

    public function test_it_refreshes_an_access_token(): void
    {
        $this->configureGoogle();

        Http::fake([
            'oauth2.googleapis.com/token' => Http::response([
                'access_token' => 'new-access-123',
                'expires_in' => 3600,
                'scope' => 'https://www.googleapis.com/auth/webmasters.readonly',
            ]),
        ]);

        $tokens = (new GoogleOAuthService)->refreshAccessToken('refresh-123');

        $this->assertSame('new-access-123', $tokens['access_token']);

        Http::assertSent(fn ($request) => $request['grant_type'] === 'refresh_token'
            && $request['refresh_token'] === 'refresh-123');
    }
}
