<?php

namespace App\Support;

use App\Models\SmsSetting;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * The single place in the app that actually talks to sms.ir - every
 * feature that needs to send an SMS (starting with event reminders, see
 * App\Notifications\Channels\SmsChannel) goes through here instead of
 * calling the gateway itself, so the API key/line number only ever live
 * in one place (SmsSetting, edited from "تنظیمات پیامک" in the panel).
 *
 * sms.ir's REST API isn't reachable from this development sandbox to
 * verify against live docs, so this targets their documented "ارسال
 * تکی" (single/bulk) endpoint from public API references: POST
 * https://api.sms.ir/v1/send/bulk, authenticated with an X-API-KEY
 * header, body {lineNumber, messageText, mobiles: [...]}. Confirm this
 * against a real sms.ir account before relying on it in production -
 * adjust the endpoint/body below if their actual response differs.
 */
class SmsGateway
{
    private const ENDPOINT = 'https://api.sms.ir/v1/send/bulk';

    /**
     * Sends $text to $mobile. Returns false (and logs why) instead of
     * throwing whenever sending isn't possible - a failed/unconfigured
     * SMS gateway must never break the notification it's one channel of.
     */
    public static function send(string $mobile, string $text): bool
    {
        $settings = SmsSetting::current();

        if (! $settings->isUsable()) {
            Log::info('SmsGateway: skipped, gateway is disabled or not configured.');

            return false;
        }

        try {
            $response = Http::withHeaders([
                'X-API-KEY' => $settings->api_key,
                'Accept' => 'application/json',
            ])
                ->timeout(10)
                ->post(self::ENDPOINT, [
                    'lineNumber' => $settings->line_number,
                    'messageText' => $text,
                    'mobiles' => [$mobile],
                ]);
        } catch (\Throwable $exception) {
            Log::warning('SmsGateway: request to sms.ir failed.', ['exception' => $exception->getMessage()]);

            return false;
        }

        if (! $response->successful()) {
            Log::warning('SmsGateway: sms.ir returned an error.', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return false;
        }

        return true;
    }
}
