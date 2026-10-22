<?php

namespace App\Domain\CustomerApp;

use App\Models\CustomerAppGoogleSetting;
use Illuminate\Support\Facades\Http;

/**
 * "ورود با گوگل" for the وب‌اپ مشتریان - plain HTTPS OAuth 2.0 against
 * Google's own endpoints, the same pattern App\Domain\Seo\GoogleOAuthService
 * already uses for Search Console (no laravel/socialite or google/apiclient
 * dependency needed for a handful of endpoints). Unlike that service, this
 * one never stores a refresh token: a customer login only needs a one-time
 * read of their Google profile (email/name) to identify or create their
 * Guest account, not ongoing API access on their behalf.
 */
class CustomerGoogleOAuthService
{
    private const AUTH_URL = 'https://accounts.google.com/o/oauth2/v2/auth';

    private const TOKEN_URL = 'https://oauth2.googleapis.com/token';

    private const USERINFO_URL = 'https://www.googleapis.com/oauth2/v3/userinfo';

    public function buildAuthorizationUrl(string $state): string
    {
        $settings = $this->settingsOrFail();

        $query = http_build_query([
            'client_id' => $settings->client_id,
            'redirect_uri' => $this->redirectUri(),
            'response_type' => 'code',
            'scope' => 'openid email profile',
            'state' => $state,
        ]);

        return self::AUTH_URL.'?'.$query;
    }

    /**
     * @return array{sub: string, email: ?string, name: ?string, picture: ?string}
     */
    public function resolveUserFromCode(string $code): array
    {
        $settings = $this->settingsOrFail();

        $tokenResponse = Http::asForm()->timeout(20)->connectTimeout(5)->post(self::TOKEN_URL, [
            'client_id' => $settings->client_id,
            'client_secret' => $settings->client_secret,
            'redirect_uri' => $this->redirectUri(),
            'grant_type' => 'authorization_code',
            'code' => $code,
        ]);

        if ($tokenResponse->failed()) {
            throw CustomerAuthException::googleRequestFailed($tokenResponse->json('error_description') ?? $tokenResponse->json('error') ?? "HTTP {$tokenResponse->status()}");
        }

        $accessToken = $tokenResponse->json('access_token');

        $userResponse = Http::withToken($accessToken)->timeout(20)->connectTimeout(5)->get(self::USERINFO_URL);

        if ($userResponse->failed()) {
            throw CustomerAuthException::googleRequestFailed('دریافت اطلاعات حساب گوگل ناموفق بود.');
        }

        return [
            'sub' => $userResponse->json('sub'),
            'email' => $userResponse->json('email'),
            'name' => $userResponse->json('name'),
            'picture' => $userResponse->json('picture'),
        ];
    }

    public function redirectUri(): string
    {
        return route('customer-app.auth.google.callback');
    }

    private function settingsOrFail(): CustomerAppGoogleSetting
    {
        $settings = CustomerAppGoogleSetting::current();

        if (! $settings->isConfigured()) {
            throw CustomerAuthException::googleNotConfigured();
        }

        return $settings;
    }
}
