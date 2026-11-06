<?php

namespace FireflyDistill\Rest;

use FireflyDistill\Security\Hmac;
use WP_REST_Request;

final class Auth
{
    public static function adminOrHmac(WP_REST_Request $request): bool
    {
        if (current_user_can('manage_options')) {
            return true;
        }
        return self::hmac($request);
    }

    public static function adminOnly(): bool
    {
        return current_user_can('manage_options');
    }

    public static function hmac(WP_REST_Request $request): bool
    {
        $headers = [];
        foreach ([Hmac::HEADER_KEY, Hmac::HEADER_TS, Hmac::HEADER_SIG] as $name) {
            $headers[$name] = (string) $request->get_header($name);
        }
        $uri = (string) ($_SERVER['REQUEST_URI'] ?? $request->get_route());
        $path = (string) (parse_url($uri, PHP_URL_PATH) ?: $request->get_route());
        $body = (string) $request->get_body();
        return Hmac::verifyHeaders($headers, (string) $request->get_method(), $path, $body);
    }

    public static function hmacAndJobToken(WP_REST_Request $request): bool
    {
        if (!self::hmac($request)) {
            return false;
        }
        $token = (string) ($request->get_param('job_token') ?? '');
        $expected = (string) get_transient('ffd_job_token_' . md5($token));
        if ($token === '' || $expected === '' || !hash_equals($expected, $token)) {
            return false;
        }
        return true;
    }
}
