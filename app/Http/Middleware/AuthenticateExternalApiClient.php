<?php

namespace App\Http\Middleware;

use App\Models\ExternalApiClient;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpFoundation\Response;

/**
 * Every route under routes/api.php's v1/external group goes through this
 * first - a POS terminal or an online-sales integration authenticates
 * with an X-API-Key + X-API-Secret pair (section 102), never a user
 * session/cookie. The resolved client is bound into the request so
 * controllers can read it via $request->attributes->get('external_api_client').
 */
class AuthenticateExternalApiClient
{
    public function handle(Request $request, Closure $next): Response
    {
        $apiKey = $request->header('X-API-Key');
        $apiSecret = $request->header('X-API-Secret');

        if (! $apiKey || ! $apiSecret) {
            return $this->error('UNAUTHORIZED', 'کلید و رمز API الزامی است.', 401);
        }

        $client = ExternalApiClient::where('api_key', $apiKey)->first();

        if (! $client || ! $client->is_active || ! $client->verifySecret($apiSecret)) {
            return $this->error('UNAUTHORIZED', 'اطلاعات احراز هویت نامعتبر است.', 401);
        }

        $rateLimitKey = "external-api:{$client->id}";

        $limit = (int) $client->rate_limit_per_minute;

        if (RateLimiter::tooManyAttempts($rateLimitKey, $limit)) {
            $retryAfter = RateLimiter::availableIn($rateLimitKey);

            return $this->error('RATE_LIMITED', 'تعداد درخواست‌ها بیش از حد مجاز است.', 429)
                ->withHeaders(['Retry-After' => $retryAfter, 'X-RateLimit-Limit' => $limit, 'X-RateLimit-Remaining' => 0]);
        }

        RateLimiter::hit($rateLimitKey, 60);

        $client->update(['last_used_at' => now()]);
        $request->attributes->set('external_api_client', $client);

        // Tell the integration how much room it has left, so it can slow
        // down by itself before it starts getting 429s.
        $response = $next($request);
        $response->headers->set('X-RateLimit-Limit', (string) $limit);
        $response->headers->set('X-RateLimit-Remaining', (string) RateLimiter::remaining($rateLimitKey, $limit));

        return $response;
    }

    private function error(string $code, string $message, int $status): JsonResponse
    {
        return response()->json(['error' => ['code' => $code, 'message' => $message]], $status);
    }
}
