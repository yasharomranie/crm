<?php

namespace App\Domain\Payments;

use App\Models\PaymentGatewaySetting;
use Illuminate\Support\Facades\Http;

/**
 * Thin client for Zarinpal's v4 REST API (request -> redirect -> verify).
 * Zarinpal works in rials while the app works in toman, so amounts are
 * multiplied by ten on the way out.
 */
class ZarinpalGateway
{
    public function __construct(private readonly PaymentGatewaySetting $settings) {}

    public static function make(): self
    {
        return new self(PaymentGatewaySetting::current());
    }

    /**
     * @return string the authority code identifying the payment
     *
     * @throws GatewayException
     */
    public function requestPayment(int $amountToman, string $callbackUrl, string $description): string
    {
        $data = $this->post('request', [
            'amount' => $amountToman * 10,
            'callback_url' => $callbackUrl,
            'description' => $description,
        ]);

        if (($data['data']['code'] ?? null) !== 100 || blank($data['data']['authority'] ?? null)) {
            throw new GatewayException($this->errorMessage($data, 'ایجاد تراکنش در درگاه ناموفق بود.'));
        }

        return (string) $data['data']['authority'];
    }

    public function startPayUrl(string $authority): string
    {
        return $this->host().'/pg/StartPay/'.$authority;
    }

    /**
     * @return array{ref_id: string, card_pan: ?string} the gateway confirmed the payment
     *
     * @throws GatewayException when the gateway says the payment was not made, or cannot be reached
     */
    public function verify(int $amountToman, string $authority): array
    {
        $data = $this->post('verify', [
            'amount' => $amountToman * 10,
            'authority' => $authority,
        ]);

        // 100 = verified now, 101 = already verified earlier.
        if (! in_array($data['data']['code'] ?? null, [100, 101], true)) {
            throw new GatewayException($this->errorMessage($data, 'پرداخت توسط درگاه تأیید نشد.'));
        }

        return [
            'ref_id' => (string) ($data['data']['ref_id'] ?? ''),
            'card_pan' => $data['data']['card_pan'] ?? null,
        ];
    }

    private function host(): string
    {
        return $this->settings->is_sandbox ? 'https://sandbox.zarinpal.com' : 'https://www.zarinpal.com';
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     *
     * @throws GatewayException
     */
    private function post(string $endpoint, array $payload): array
    {
        $apiHost = $this->settings->is_sandbox ? 'https://sandbox.zarinpal.com' : 'https://api.zarinpal.com';

        try {
            $response = Http::acceptJson()
                ->timeout(15)
                ->post("{$apiHost}/pg/v4/payment/{$endpoint}.json", ['merchant_id' => $this->settings->merchant_id, ...$payload]);
        } catch (\Throwable $exception) {
            throw new GatewayException('ارتباط با درگاه پرداخت برقرار نشد.', isTransient: true, previous: $exception);
        }

        // Zarinpal answers errors with a non-2xx status and an "errors"
        // object, and successes with a "data" object - both are JSON.
        return $response->json() ?? [];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function errorMessage(array $data, string $fallback): string
    {
        $message = $data['errors']['message'] ?? null;

        return filled($message) ? "{$fallback} ({$message})" : $fallback;
    }
}
