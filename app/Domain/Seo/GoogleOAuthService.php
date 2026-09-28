<?php

namespace App\Domain\Seo;

use App\Exceptions\SeoGoogleException;
use Illuminate\Support\Facades\Http;

/**
 * Plain HTTPS OAuth 2.0 against Google's own endpoints - no google/apiclient
 * or Socialite dependency. Google's OAuth and REST APIs (this, and
 * SearchConsoleClient below) are ordinary JSON-over-HTTP, so this follows
 * the exact same Http-facade pattern as AiProvider::chatCompletion()
 * instead of pulling in a heavy SDK for what's a handful of endpoints.
 */
class GoogleOAuthService
{
    private const AUTH_URL = 'https://accounts.google.com/o/oauth2/v2/auth';

    private const TOKEN_URL = 'https://oauth2.googleapis.com/token';

    /**
     * @param  array<int, string>  $scopes
     */
    public function buildAuthorizationUrl(array $scopes, string $state): string
    {
        $this->ensureConfigured();

        $query = http_build_query([
            'client_id' => config('services.google.client_id'),
            'redirect_uri' => config('services.google.redirect'),
            'response_type' => 'code',
            'access_type' => 'offline',
            // Forces Google to always return a refresh_token, even if this
            // Google account already granted this app access before - the
            // default behavior only issues one on the very first consent,
            // which would silently break reconnecting an expired project.
            'prompt' => 'consent',
            'scope' => implode(' ', $scopes),
            'state' => $state,
        ]);

        return self::AUTH_URL.'?'.$query;
    }

    /**
     * @return array{access_token: string, refresh_token: ?string, expires_in: int, scope: string}
     */
    public function exchangeCodeForToken(string $code): array
    {
        $this->ensureConfigured();

        $response = Http::asForm()->post(self::TOKEN_URL, [
            'client_id' => config('services.google.client_id'),
            'client_secret' => config('services.google.client_secret'),
            'redirect_uri' => config('services.google.redirect'),
            'grant_type' => 'authorization_code',
            'code' => $code,
        ]);

        if ($response->failed()) {
            throw SeoGoogleException::requestFailed($response->json('error_description') ?? $response->json('error') ?? "HTTP {$response->status()}");
        }

        return $response->json();
    }

    /**
     * @return array{access_token: string, expires_in: int, scope: string}
     */
    public function refreshAccessToken(string $refreshToken): array
    {
        $this->ensureConfigured();

        $response = Http::asForm()->post(self::TOKEN_URL, [
            'client_id' => config('services.google.client_id'),
            'client_secret' => config('services.google.client_secret'),
            'grant_type' => 'refresh_token',
            'refresh_token' => $refreshToken,
        ]);

        if ($response->failed()) {
            throw SeoGoogleException::requestFailed($response->json('error_description') ?? $response->json('error') ?? "HTTP {$response->status()}");
        }

        return $response->json();
    }

    private function ensureConfigured(): void
    {
        if (blank(config('services.google.client_id')) || blank(config('services.google.client_secret'))) {
            throw SeoGoogleException::notConfigured();
        }
    }
}
