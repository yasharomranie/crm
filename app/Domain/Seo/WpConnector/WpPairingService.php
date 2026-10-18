<?php

namespace App\Domain\Seo\WpConnector;

use App\Models\SeoProject;
use App\Models\SeoWpSite;
use Illuminate\Support\Str;

/**
 * Connects a WordPress site to an SEO project without anyone typing a
 * password: the CRM shows a one-time code, the plugin trades it for a
 * signing secret. Only a hash of the code is kept, it works once, and it
 * expires after a day.
 */
class WpPairingService
{
    public const CODE_TTL_HOURS = 24;

    /**
     * @return string the code to type into the plugin, shown only now
     */
    public function issueCode(SeoProject $project): string
    {
        $code = Str::upper(Str::random(12));

        SeoWpSite::updateOrCreate(
            ['seo_project_id' => $project->id],
            ['pairing_code_hash' => $this->hash($code), 'pairing_expires_at' => now()->addHours(self::CODE_TTL_HOURS)],
        );

        return implode('-', str_split($code, 4));
    }

    /**
     * @param  array{site_url: string, plugin_version?: ?string, wp_version?: ?string, php_version?: ?string, seo_plugin?: ?string, woocommerce?: bool, scopes?: list<string>}  $info
     * @return array{site: SeoWpSite, secret: string}
     */
    public function pair(string $code, array $info): array
    {
        $site = SeoWpSite::query()
            ->where('pairing_code_hash', $this->hash($code))
            ->where('pairing_expires_at', '>', now())
            ->first();

        if (! $site) {
            throw WpConnectorException::invalidCode();
        }

        $secret = bin2hex(random_bytes(32));

        $site->update([
            'secret' => $secret,
            'status' => SeoWpSite::STATUS_CONNECTED,
            'pairing_code_hash' => null,
            'pairing_expires_at' => null,
            'site_url' => $info['site_url'],
            'plugin_version' => $info['plugin_version'] ?? null,
            'wp_version' => $info['wp_version'] ?? null,
            'php_version' => $info['php_version'] ?? null,
            'seo_plugin' => $info['seo_plugin'] ?? null,
            'has_woocommerce' => (bool) ($info['woocommerce'] ?? false),
            'scopes' => $info['scopes'] ?? [],
            'last_seen_at' => now(),
            'last_error' => null,
        ]);

        return ['site' => $site, 'secret' => $secret];
    }

    public function disconnect(SeoWpSite $site): void
    {
        $site->update([
            'status' => SeoWpSite::STATUS_DISCONNECTED,
            'secret' => null,
            'pairing_code_hash' => null,
            'pairing_expires_at' => null,
        ]);
    }

    private function hash(string $code): string
    {
        return hash('sha256', Str::upper(preg_replace('/[^A-Za-z0-9]/', '', $code) ?? ''));
    }
}
