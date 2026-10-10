<?php

namespace App\Domain\Ai;

use Illuminate\Support\Facades\Http;

/**
 * Fetches a single public web page and returns its readable text, for the
 * fetch_url tool a custom AI assistant profile can call
 * (HasCustomAssistantChatEngine). Unlike TechnicalSeoAuditor's crawler -
 * which only ever visits a URL the project's own owner configured - the
 * URL here is chosen by the AI model from free-form chat text, so it must
 * be treated as untrusted input: this validates the URL resolves to a
 * public IP before ever making the request, to block SSRF attempts
 * against the server's own loopback/private network or cloud metadata
 * endpoints (e.g. 169.254.169.254).
 *
 * Known limitation: the allow-list check and the actual HTTP request are
 * two separate DNS lookups, so a host whose DNS record changes between
 * them (DNS rebinding) could in theory slip through. Accepted as a Phase
 * 1 trade-off - closing it fully would need a custom resolver that pins
 * the validated IP for the request itself.
 */
class SafeUrlFetcher
{
    private const TIMEOUT_SECONDS = 10;

    private const MAX_CONTENT_LENGTH = 8000;

    private const ALLOWED_PORTS = [80, 443];

    /**
     * @return array{url: string, content: string}|array{error: string}
     */
    public function fetch(string $url): array
    {
        if (! $this->isPubliclyRoutable($url)) {
            return ['error' => 'این آدرس مجاز نیست - فقط آدرس‌های عمومی http/https پذیرفته می‌شوند.'];
        }

        try {
            $response = Http::timeout(self::TIMEOUT_SECONDS)
                ->withOptions(['allow_redirects' => false])
                ->get($url);
        } catch (\Throwable $exception) {
            return ['error' => 'دریافت صفحه با خطا مواجه شد: '.$exception->getMessage()];
        }

        if ($response->failed()) {
            return ['error' => "درخواست با کد وضعیت {$response->status()} پاسخ داد."];
        }

        return [
            'url' => $url,
            'content' => $this->extractReadableText($response->body()),
        ];
    }

    private function isPubliclyRoutable(string $url): bool
    {
        $parts = parse_url($url);
        $host = $parts['host'] ?? null;

        if (! $parts || ! in_array($parts['scheme'] ?? null, ['http', 'https'], true) || blank($host)) {
            return false;
        }

        if (isset($parts['port']) && ! in_array($parts['port'], self::ALLOWED_PORTS, true)) {
            return false;
        }

        $ips = $this->resolveIps($host);

        if ($ips === []) {
            return false;
        }

        foreach ($ips as $ip) {
            if (! filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return list<string>
     */
    private function resolveIps(string $host): array
    {
        if (filter_var($host, FILTER_VALIDATE_IP)) {
            return [$host];
        }

        $records = @dns_get_record($host, DNS_A + DNS_AAAA);

        if ($records === false || $records === []) {
            return [];
        }

        return array_values(array_filter(array_map(
            fn (array $record) => $record['ip'] ?? $record['ipv6'] ?? null,
            $records,
        )));
    }

    private function extractReadableText(string $html): string
    {
        $html = preg_replace('#<(script|style)\b[^>]*>.*?</\1>#is', ' ', $html) ?? '';
        $text = strip_tags($html);
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5);
        $text = trim(preg_replace('/\s+/u', ' ', $text) ?? '');

        return mb_substr($text, 0, self::MAX_CONTENT_LENGTH);
    }
}
