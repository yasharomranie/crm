<?php

namespace App\Domain\Seo;

use App\Exceptions\SeoGoogleException;
use Illuminate\Support\Facades\Http;

/**
 * Unlike SearchConsoleClient's Search Analytics ("how did this query/page
 * perform"), the URL Inspection API answers a different question - "is
 * this exact URL indexed right now, and if not, why" - and lives under
 * the newer searchconsole.googleapis.com host, not the legacy webmasters
 * v3 one. Same OAuth scope covers both
 * (https://www.googleapis.com/auth/webmasters.readonly), so no separate
 * consent/reconnect is needed.
 */
class UrlInspectionClient
{
    private const INSPECT_URL = 'https://searchconsole.googleapis.com/v1/urlInspection/index:inspect';

    public function __construct(private readonly string $accessToken) {}

    /**
     * @return array<string, mixed> the raw "inspectionResult" object
     */
    public function inspect(string $inspectionUrl, string $siteUrl): array
    {
        $response = Http::withToken($this->accessToken)
            ->post(self::INSPECT_URL, [
                'inspectionUrl' => $inspectionUrl,
                'siteUrl' => $siteUrl,
            ]);

        if ($response->failed()) {
            throw SeoGoogleException::requestFailed($response->json('error.message') ?? "HTTP {$response->status()}");
        }

        return $response->json('inspectionResult', []);
    }
}
