<?php

namespace App\Http\Middleware;

use App\Domain\Seo\WpConnector\WpSignature;
use App\Models\SeoWpSite;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpFoundation\Response;

/**
 * Every call from the WordPress connector plugin after pairing: which site
 * (X-Wp-Site), when (X-Wp-Timestamp) and an HMAC of the exact body
 * (X-Wp-Signature). A site that was disconnected, or whose secret no longer
 * matches, is refused.
 */
class AuthenticateWpConnector
{
    public const REQUESTS_PER_MINUTE = 120;

    public function handle(Request $request, Closure $next): Response
    {
        $siteId = (int) $request->header('X-Wp-Site');
        $timestamp = (int) $request->header('X-Wp-Timestamp');
        $signature = (string) $request->header('X-Wp-Signature');

        $site = $siteId ? SeoWpSite::find($siteId) : null;

        if (! $site || ! $site->isConnected() || $signature === '') {
            return $this->error('UNAUTHORIZED', 'اتصال این سایت برقرار نیست.', 401);
        }

        if (! WpSignature::isFresh($timestamp)) {
            return $this->error('STALE_TIMESTAMP', 'ساعت سایت با سرور CRM هم‌خوان نیست.', 401);
        }

        if (! WpSignature::isValid($site->secret, $timestamp, $request->getContent(), $signature)) {
            return $this->error('BAD_SIGNATURE', 'امضای درخواست نامعتبر است.', 401);
        }

        $key = "wp-connector:{$site->id}";

        if (RateLimiter::tooManyAttempts($key, self::REQUESTS_PER_MINUTE)) {
            return $this->error('RATE_LIMITED', 'درخواست‌ها زیاد است؛ کمی بعد دوباره بفرستید.', 429)
                ->withHeaders(['Retry-After' => RateLimiter::availableIn($key)]);
        }

        RateLimiter::hit($key, 60);
        $request->attributes->set('seo_wp_site', $site);

        return $next($request);
    }

    private function error(string $code, string $message, int $status): JsonResponse
    {
        return response()->json(['error' => ['code' => $code, 'message' => $message]], $status);
    }
}
