<?php

namespace App\Http\Controllers\Api\V1\External;

use App\Domain\Ticketing\GateValidationService;
use App\Http\Controllers\Controller;
use App\Models\ExternalApiClient;
use App\Models\Gate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * For a standalone/third-party scanner device that can't run the
 * Filament Gate Scanner page (see GateScanner.php) - the exact same
 * GateValidationService is called either way, so a scan made through
 * this endpoint is validated by, and logged against, identical rules.
 */
class GateScanController extends Controller
{
    public function store(Request $request, string $gateCode): JsonResponse
    {
        /** @var ExternalApiClient $client */
        $client = $request->attributes->get('external_api_client');

        if (! $client->hasPermission('gate.scan')) {
            return $this->error('FORBIDDEN', 'این اکانت مجاز به اسکن بلیت نیست.', 403);
        }

        $data = $request->validate([
            'qr_token' => ['required', 'string'],
            'device_identifier' => ['nullable', 'string', 'max:100'],
        ]);

        $gate = Gate::where('code', $gateCode)->where('is_active', true)->first();

        if (! $gate) {
            return $this->error('NOT_FOUND', 'گیتی با این کد یافت نشد.', 404);
        }

        $result = app(GateValidationService::class)->scan($data['qr_token'], $gate, null, [
            'device_identifier' => $data['device_identifier'] ?? "external-api:{$client->client_name}",
            'ip_address' => $request->ip(),
        ]);

        return response()->json([
            'result' => $result['result']->value,
            'reason' => $result['reason']?->getLabel(),
            'ticket_number' => $result['ticket']?->ticket_number,
            'usage_number' => $result['usage_number'],
        ]);
    }

    private function error(string $code, string $message, int $status): JsonResponse
    {
        return response()->json(['error' => ['code' => $code, 'message' => $message]], $status);
    }
}
