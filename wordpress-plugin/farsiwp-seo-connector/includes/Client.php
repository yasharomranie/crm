<?php

namespace FarsiWP\SeoConnector;

/**
 * Talks to the CRM: every call after pairing carries the site id, a
 * timestamp and an HMAC of the exact body.
 */
class Client
{
    /**
     * @return array{code: int, body: ?array, error: ?string, retry_after: ?int}
     */
    public static function send($path, array $payload, $signed = true)
    {
        $settings = Settings::get();
        $state = Settings::state();
        $body = wp_json_encode($payload, JSON_UNESCAPED_UNICODE);
        $headers = ['Content-Type' => 'application/json', 'Accept' => 'application/json'];

        if ($signed) {
            $timestamp = time();
            $headers['X-Wp-Site'] = (string) $state['site_id'];
            $headers['X-Wp-Timestamp'] = (string) $timestamp;
            $headers['X-Wp-Signature'] = Signer::sign($state['secret'], $timestamp, $body);
        }

        $response = wp_remote_post(rtrim($settings['crm_url'], '/').'/api/v1/wp-connector'.$path, [
            'timeout' => 25,
            'headers' => $headers,
            'body' => $body,
        ]);

        if (is_wp_error($response)) {
            return ['code' => 0, 'body' => null, 'error' => $response->get_error_message(), 'retry_after' => null];
        }

        $decoded = json_decode(wp_remote_retrieve_body($response), true);
        $code = (int) wp_remote_retrieve_response_code($response);
        $retryAfter = wp_remote_retrieve_header($response, 'retry-after');

        return [
            'code' => $code,
            'body' => is_array($decoded) ? $decoded : null,
            'error' => $code >= 400 ? (isset($decoded['error']['message']) ? $decoded['error']['message'] : 'HTTP '.$code) : null,
            'retry_after' => $retryAfter !== '' ? (int) $retryAfter : null,
        ];
    }
}
