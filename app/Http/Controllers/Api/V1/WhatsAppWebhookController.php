<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessWhatsAppInboundMessageJob;
use App\Models\WhatsAppBusinessSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * The two endpoints Meta's WhatsApp Cloud API needs on a webhook:
 * verify() for the one-time handshake performed when the URL + verify
 * token are entered in the Meta developer dashboard, and receive() for
 * every actual incoming-message notification after that. Both are public
 * (no session/API-key auth - Meta calls these directly), gated instead by
 * WhatsAppBusinessSetting::isUsable() and, for verify(), the configured
 * verify_token itself.
 */
class WhatsAppWebhookController extends Controller
{
    public function verify(Request $request): Response
    {
        $setting = WhatsAppBusinessSetting::current();

        if (! $setting->isUsable()) {
            return response('', 403);
        }

        if ($request->query('hub_mode') !== 'subscribe' || $request->query('hub_verify_token') !== $setting->verify_token) {
            return response('', 403);
        }

        return response((string) $request->query('hub_challenge'), 200);
    }

    public function receive(Request $request): JsonResponse
    {
        $setting = WhatsAppBusinessSetting::current();

        if (! $setting->isUsable()) {
            return response()->json(['status' => 'ignored']);
        }

        $entries = $request->input('entry', []);

        foreach ($entries as $entry) {
            foreach ($entry['changes'] ?? [] as $change) {
                $value = $change['value'] ?? [];
                $contactsByWaId = collect($value['contacts'] ?? [])->keyBy('wa_id');

                foreach ($value['messages'] ?? [] as $message) {
                    $waId = $message['from'] ?? null;
                    $text = $message['text']['body'] ?? null;

                    if (! $waId || ! $text) {
                        continue;
                    }

                    $contactName = $contactsByWaId->get($waId)['profile']['name'] ?? null;

                    ProcessWhatsAppInboundMessageJob::dispatch($waId, $text, $contactName);
                }
            }
        }

        // Meta requires a fast 200 acknowledgment, regardless of what the
        // payload contained - the actual work happens in the dispatched
        // job(s) above.
        return response()->json(['status' => 'received']);
    }
}
