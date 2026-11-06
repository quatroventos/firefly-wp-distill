<?php

namespace FireflyDistill\Security;

final class Hmac
{
    public const HEADER_KEY = 'X-FFD-Key';
    public const HEADER_TS = 'X-FFD-Timestamp';
    public const HEADER_SIG = 'X-FFD-Signature';
    public const WINDOW_SECONDS = 300;

    public static function secret(): string
    {
        if (defined('FFD_HMAC_SECRET') && is_string(FFD_HMAC_SECRET) && FFD_HMAC_SECRET !== '') {
            return FFD_HMAC_SECRET;
        }
        if (!function_exists('get_option')) {
            return '';
        }
        $stored = get_option('ffd_hmac_secret', '');
        return is_string($stored) ? $stored : '';
    }

    public static function siteKeyId(): string
    {
        if (!function_exists('get_option')) {
            return '';
        }
        $settings = get_option('ffd_settings', []);
        return is_array($settings) ? (string) ($settings['site_key_id'] ?? '') : '';
    }

    public static function sign(string $secret, string $timestamp, string $method, string $path, string $body): string
    {
        $payload = $timestamp . "\n" . strtoupper($method) . "\n" . $path . "\n" . hash('sha256', $body);
        return hash_hmac('sha256', $payload, $secret);
    }

    public static function verify(string $secret, string $timestamp, string $method, string $path, string $body, string $signature, ?int $now = null): bool
    {
        if ($secret === '' || $signature === '' || $timestamp === '') {
            return false;
        }
        if (!ctype_digit($timestamp)) {
            return false;
        }
        $now = $now ?? time();
        if (abs($now - (int) $timestamp) > self::WINDOW_SECONDS) {
            return false;
        }
        $expected = self::sign($secret, $timestamp, $method, $path, $body);
        return hash_equals($expected, $signature);
    }

    /**
     * @param array<string,string> $headers
     */
    public static function verifyHeaders(array $headers, string $method, string $path, string $body, ?int $now = null): bool
    {
        $key = $headers[self::HEADER_KEY] ?? $headers['x-ffd-key'] ?? '';
        $ts = $headers[self::HEADER_TS] ?? $headers['x-ffd-timestamp'] ?? '';
        $sig = $headers[self::HEADER_SIG] ?? $headers['x-ffd-signature'] ?? '';
        if ($key === '' || $key !== self::siteKeyId()) {
            return false;
        }
        if (!self::verify(self::secret(), $ts, $method, $path, $body, $sig, $now)) {
            return false;
        }
        return self::consumeNonce($sig);
    }

    public static function consumeNonce(string $signature): bool
    {
        if (!function_exists('get_transient')) {
            return true;
        }
        $cacheKey = 'ffd_replay_' . md5($signature);
        if (get_transient($cacheKey)) {
            return false;
        }
        set_transient($cacheKey, 1, self::WINDOW_SECONDS);
        return true;
    }
}
