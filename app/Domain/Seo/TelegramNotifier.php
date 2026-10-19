<?php

namespace App\Domain\Seo;

use App\Models\SeoAlertSetting;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Sends a short text to the Telegram chat chosen in "تنظیمات هشدار سئو".
 * Telegram may be unreachable from some Iranian hosts, so the API address
 * can be pointed at a relay in the settings; a failed send is never an
 * error for whatever raised the alert.
 */
class TelegramNotifier
{
    private const DEFAULT_API_BASE = 'https://api.telegram.org';

    /**
     * @return bool whether Telegram accepted the message
     */
    public function send(string $text, ?SeoAlertSetting $settings = null): bool
    {
        $settings ??= SeoAlertSetting::current();

        if (! $settings->telegramIsUsable()) {
            return false;
        }

        $base = rtrim(filled($settings->telegram_api_base) ? $settings->telegram_api_base : self::DEFAULT_API_BASE, '/');

        try {
            return Http::timeout(10)
                ->asJson()
                ->post("{$base}/bot{$settings->telegram_bot_token}/sendMessage", [
                    'chat_id' => $settings->telegram_chat_id,
                    'text' => $text,
                    'disable_web_page_preview' => true,
                ])
                ->successful();
        } catch (Throwable $exception) {
            Log::warning("Telegram send failed: {$exception->getMessage()}");

            return false;
        }
    }
}
