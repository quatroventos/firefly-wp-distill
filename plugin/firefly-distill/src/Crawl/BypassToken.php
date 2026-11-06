<?php

namespace FireflyDistill\Crawl;

use FireflyDistill\Hosts\HostValidator;
use FireflyDistill\Security\Hmac;

final class BypassToken
{
    public const QUERY = 'firefly-distill';
    public const QUERY_VALUE = 'origin';
    public const MAX_TTL = 7200;

    public function register(): void
    {
        add_action('init', [$this, 'handle'], 0);
    }

    public function handle(): void
    {
        if (!isset($_GET[self::QUERY])) {
            return;
        }
        if ((string) $_GET[self::QUERY] !== self::QUERY_VALUE) {
            return;
        }

        $host = HostValidator::normalize((string) ($_SERVER['HTTP_HOST'] ?? ''));
        $path = HostValidator::normalizePath((string) (parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/'));
        $sig = (string) ($_GET['ff_sig'] ?? '');
        $exp = (string) ($_GET['ff_exp'] ?? '');

        if (!self::validate($host, $path, $sig, $exp)) {
            status_header(403);
            nocache_headers();
            wp_die('firefly-distill: bypass inválido', 'Forbidden', ['response' => 403]);
        }

        if (!headers_sent()) {
            header('X-FFD: origin');
        }
    }

    public static function issue(string $host, string $path, ?int $ttl = null, ?int $now = null): array
    {
        $ttl = $ttl ?? self::MAX_TTL;
        if ($ttl > self::MAX_TTL) {
            $ttl = self::MAX_TTL;
        }
        $now = $now ?? time();
        $exp = (string) ($now + $ttl);
        $host = HostValidator::normalize($host);
        $path = HostValidator::normalizePath($path);
        return [
            'ff_sig' => self::signature($host, $path, $exp),
            'ff_exp' => $exp,
        ];
    }

    public static function validate(string $host, string $path, string $sig, string $exp, ?int $now = null): bool
    {
        $now = $now ?? time();
        if ($sig === '' || $exp === '' || !ctype_digit($exp)) {
            return false;
        }
        $expInt = (int) $exp;
        if ($expInt < $now || $expInt > $now + self::MAX_TTL) {
            return false;
        }
        $host = HostValidator::normalize($host);
        $path = HostValidator::normalizePath($path);
        $expected = self::signature($host, $path, $exp);
        return hash_equals($expected, $sig);
    }

    public static function signature(string $host, string $path, string $exp): string
    {
        return hash_hmac('sha256', $host . "\n" . $path . "\n" . $exp, Hmac::secret());
    }

    public static function originUrl(string $publicUrl, string $host, string $path): string
    {
        $token = self::issue($host, $path);
        $sep = str_contains($publicUrl, '?') ? '&' : '?';
        return $publicUrl . $sep . self::QUERY . '=' . self::QUERY_VALUE
            . '&ff_sig=' . rawurlencode($token['ff_sig'])
            . '&ff_exp=' . rawurlencode($token['ff_exp']);
    }
}
