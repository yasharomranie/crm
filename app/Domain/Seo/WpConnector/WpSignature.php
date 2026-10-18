<?php

namespace App\Domain\Seo\WpConnector;

/**
 * How the connector plugin proves a request is its own: an HMAC-SHA256 of
 * "{timestamp}.{raw body}" with the secret handed over at pairing. The
 * timestamp keeps a captured request from being replayed later.
 */
class WpSignature
{
    /** How far the plugin's clock may differ from ours, in seconds. */
    public const TOLERANCE_SECONDS = 300;

    public static function sign(string $secret, int $timestamp, string $body): string
    {
        return hash_hmac('sha256', $timestamp.'.'.$body, $secret);
    }

    public static function isValid(string $secret, int $timestamp, string $body, string $signature): bool
    {
        return hash_equals(self::sign($secret, $timestamp, $body), strtolower($signature));
    }

    public static function isFresh(int $timestamp): bool
    {
        return abs(time() - $timestamp) <= self::TOLERANCE_SECONDS;
    }
}
