<?php

namespace App\Domain\WebApp;

/**
 * Checks whether a custom domain's DNS has actually been pointed at this
 * app, so App\Filament\Pages\WebAppDomainSettings can tell the admin
 * "connected" vs. "still waiting on DNS" instead of trusting the toggle
 * alone. Accepts either shape an admin's registrar allows:
 * - a CNAME record targeting this app's own host (works for a subdomain
 *   they own, e.g. app.their-domain.com)
 * - an A record targeting this app's own IP (works for a bare/apex
 *   domain, where most registrars don't allow a CNAME at all)
 */
class CustomDomainVerifier
{
    public function verify(string $domain): bool
    {
        $target = $this->expectedCnameTarget();

        foreach ($this->lookup($domain, DNS_CNAME) as $record) {
            if (isset($record['target']) && $this->normalizeHost($record['target']) === $this->normalizeHost($target)) {
                return true;
            }
        }

        $expectedIp = $this->expectedIp();

        if ($expectedIp === null) {
            return false;
        }

        foreach ($this->lookup($domain, DNS_A) as $record) {
            if (($record['ip'] ?? null) === $expectedIp) {
                return true;
            }
        }

        return false;
    }

    /**
     * The host an admin's CNAME record should point to.
     */
    public function expectedCnameTarget(): string
    {
        return parse_url(config('app.url'), PHP_URL_HOST) ?? 'localhost';
    }

    /**
     * The IP an admin's A record should point to, when their registrar
     * won't allow a CNAME at the domain's apex/root. Null when this
     * app's own host can't currently be resolved (e.g. local dev).
     */
    public function expectedIp(): ?string
    {
        $host = $this->expectedCnameTarget();
        $ip = gethostbyname($host);

        return $ip !== $host ? $ip : null;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function lookup(string $domain, int $type): array
    {
        $records = @dns_get_record($domain, $type);

        return $records !== false ? $records : [];
    }

    private function normalizeHost(string $host): string
    {
        return rtrim(strtolower($host), '.');
    }
}
