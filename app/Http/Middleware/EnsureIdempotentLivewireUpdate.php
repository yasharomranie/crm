<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

/**
 * Makes a Livewire "update" request replay-safe when it carries the
 * X-Offline-Retry-Id header - added by offline-form-retry.blade.php only
 * to a request it's queuing after a genuine network failure, never to an
 * ordinary request.
 *
 * Without this, a queued request that the server already processed - but
 * whose success never made it back into the browser's own bookkeeping
 * before a page refresh/unload interrupted it (the queue is only
 * persisted as "done" once, after the whole flush finishes) - gets
 * replayed again on the next reconnect or refresh. Since a Filament
 * create/update action isn't itself idempotent, that replay creates a
 * second, duplicate record every time it happens.
 *
 * Every attempt to send the same queued item reuses the same id, so
 * caching the first response under that id and returning it verbatim on
 * any repeat makes an arbitrary number of retries produce the underlying
 * side effect exactly once - regardless of client-side timing.
 */
class EnsureIdempotentLivewireUpdate
{
    public function handle(Request $request, Closure $next): Response
    {
        $retryId = $request->header('X-Offline-Retry-Id');

        if (! $retryId) {
            return $next($request);
        }

        // The id itself is already an unguessable random UUID, generated
        // fresh per queued item - scoping by user is just namespacing
        // (never a substitute for it), so it deliberately doesn't fall
        // back to the session id: that can legitimately differ between
        // the original attempt and its later replay (e.g. the session
        // rotated during the outage), which would defeat the dedupe this
        // middleware exists for.
        $cacheKey = 'offline-retry:'.($request->user()?->getKey() ?? 'guest').':'.$retryId;

        // Serializes concurrent attempts with the same id (e.g. the
        // automatic reconnect flush and a manual page refresh firing at
        // nearly the same moment) so only one of them actually executes
        // the request.
        $lock = Cache::lock($cacheKey.':lock', 10);
        $lock->block(5);

        try {
            $cached = Cache::get($cacheKey);

            if ($cached) {
                return response($cached['content'], $cached['status'], $cached['headers']);
            }

            $response = $next($request);

            Cache::put($cacheKey, [
                'content' => $response->getContent(),
                'status' => $response->getStatusCode(),
                'headers' => collect($response->headers->all())->except('set-cookie')->toArray(),
            ], now()->addDay());

            return $response;
        } finally {
            $lock->release();
        }
    }
}
