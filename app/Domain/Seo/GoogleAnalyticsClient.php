<?php

namespace App\Domain\Seo;

use App\Exceptions\SeoGoogleException;
use Illuminate\Support\Facades\Http;

/**
 * Google Analytics 4 over plain HTTPS, like SearchConsoleClient: the Admin
 * API lists the properties an account can see, and the Data API answers
 * report requests. Scope: https://www.googleapis.com/auth/analytics.readonly.
 */
class GoogleAnalyticsClient
{
    private const ADMIN_URL = 'https://analyticsadmin.googleapis.com/v1beta';

    private const DATA_URL = 'https://analyticsdata.googleapis.com/v1beta';

    public function __construct(private readonly string $accessToken) {}

    /**
     * @return array<string, string> property id => "account / property" label
     */
    public function listProperties(): array
    {
        $response = Http::withToken($this->accessToken)->timeout(20)->connectTimeout(5)->get(self::ADMIN_URL.'/accountSummaries', ['pageSize' => 200]);

        if ($response->failed()) {
            throw SeoGoogleException::requestFailed($response->json('error.message') ?? "HTTP {$response->status()}");
        }

        $properties = [];

        foreach ($response->json('accountSummaries', []) as $account) {
            foreach ($account['propertySummaries'] ?? [] as $property) {
                $id = str_replace('properties/', '', (string) ($property['property'] ?? ''));

                if ($id !== '') {
                    $properties[$id] = ($account['displayName'] ?? '').' / '.($property['displayName'] ?? $id);
                }
            }
        }

        return $properties;
    }

    /**
     * @param  array<string, mixed>  $body  a Data API runReport request
     * @return array<int, array{dimensions: list<string>, metrics: list<float>}>
     */
    public function runReport(string $propertyId, array $body): array
    {
        $response = Http::withToken($this->accessToken)
            ->timeout(60)
            ->connectTimeout(5)
            ->post(self::DATA_URL."/properties/{$propertyId}:runReport", $body);

        if ($response->failed()) {
            throw SeoGoogleException::requestFailed($response->json('error.message') ?? "HTTP {$response->status()}");
        }

        return collect($response->json('rows', []))
            ->map(fn (array $row) => [
                'dimensions' => array_map(fn (array $value) => (string) ($value['value'] ?? ''), $row['dimensionValues'] ?? []),
                'metrics' => array_map(fn (array $value) => (float) ($value['value'] ?? 0), $row['metricValues'] ?? []),
            ])
            ->all();
    }
}
