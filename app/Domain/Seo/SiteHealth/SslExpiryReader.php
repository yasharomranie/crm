<?php

namespace App\Domain\Seo\SiteHealth;

use Carbon\CarbonImmutable;

/**
 * Reads when a host's HTTPS certificate expires by opening a TLS connection
 * and looking at the certificate it presents. Kept apart so tests can swap it.
 */
class SslExpiryReader
{
    public function expiresAt(string $host, int $port = 443): ?CarbonImmutable
    {
        $context = stream_context_create(['ssl' => [
            'capture_peer_cert' => true,
            'verify_peer' => false,
            'verify_peer_name' => false,
            'SNI_enabled' => true,
            'peer_name' => $host,
        ]]);

        $stream = @stream_socket_client("ssl://{$host}:{$port}", $errorCode, $errorMessage, 8, STREAM_CLIENT_CONNECT, $context);

        if (! $stream) {
            return null;
        }

        $params = stream_context_get_params($stream);
        fclose($stream);

        $certificate = $params['options']['ssl']['peer_certificate'] ?? null;
        $parsed = $certificate ? openssl_x509_parse($certificate) : false;

        return $parsed && isset($parsed['validTo_time_t']) ? CarbonImmutable::createFromTimestamp($parsed['validTo_time_t']) : null;
    }
}
