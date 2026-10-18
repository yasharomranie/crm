<?php

namespace FarsiWP\SeoConnector;

/**
 * Signs a request the way the CRM expects: HMAC-SHA256 of "{timestamp}.{body}"
 * with the secret received at pairing.
 */
class Signer
{
    public static function sign($secret, $timestamp, $body)
    {
        return hash_hmac('sha256', $timestamp.'.'.$body, $secret);
    }
}
