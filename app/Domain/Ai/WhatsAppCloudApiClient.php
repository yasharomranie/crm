<?php

namespace App\Domain\Ai;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * A thin wrapper around Meta's WhatsApp Cloud API (the "send message"
 * call) - https://developers.facebook.com/docs/whatsapp/cloud-api/reference/messages.
 * Deliberately just this one call: WhatsAppInboundMessageHandler is the
 * only caller, and it only ever needs to send a single text reply back
 * to the contact that just messaged in.
 */
class WhatsAppCloudApiClient
{
    private const API_VERSION = 'v21.0';

    private const TIMEOUT_SECONDS = 15;

    public function sendTextMessage(string $phoneNumberId, string $accessToken, string $to, string $text): bool
    {
        $response = Http::withToken($accessToken)
            ->timeout(self::TIMEOUT_SECONDS)
            ->post('https://graph.facebook.com/'.self::API_VERSION."/{$phoneNumberId}/messages", [
                'messaging_product' => 'whatsapp',
                'to' => $to,
                'type' => 'text',
                'text' => ['body' => $text],
            ]);

        if ($response->failed()) {
            Log::warning('WhatsAppCloudApiClient: send failed.', [
                'to' => $to,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return false;
        }

        return true;
    }
}
