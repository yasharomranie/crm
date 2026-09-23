<?php

namespace App\Http\Middleware;

use App\Models\ExternalApiClient;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Route-level permission gate for external clients (section 104) -
 * `->middleware('external-api.can:ticket.issue')`. A POS terminal only
 * ever gets exactly the operations an admin granted it when the
 * ExternalApiClient was created; there is no way to escalate this from
 * the request itself.
 */
class EnsureExternalApiClientCan
{
    public function handle(Request $request, Closure $next, string $operation): Response
    {
        /** @var ExternalApiClient $client */
        $client = $request->attributes->get('external_api_client');

        if (! $client->hasPermission($operation)) {
            return response()->json([
                'error' => ['code' => 'FORBIDDEN', 'message' => "این اکانت مجاز به «{$operation}» نیست."],
            ], 403);
        }

        return $next($request);
    }
}
