<?php

namespace FireflyDistill\Hosts;

final class HostValidator
{
    public const PATTERN = '/^[a-z0-9.-]+$/';

    public static function normalize(string $host): string
    {
        $host = trim($host);
        $host = strtolower($host);
        $host = preg_replace('#^https?://#', '', $host) ?? $host;
        $host = explode('/', $host)[0];
        $host = explode(':', $host)[0];
        $host = rtrim($host, '.');
        return $host;
    }

    public static function isValid(string $host): bool
    {
        if ($host === '' || strlen($host) > 253) {
            return false;
        }
        if (str_contains($host, '..') || str_starts_with($host, '-') || str_ends_with($host, '-')) {
            return false;
        }
        if (str_starts_with($host, '.') || str_ends_with($host, '.')) {
            return false;
        }
        return (bool) preg_match(self::PATTERN, $host);
    }

    public static function normalizePath(string $path): string
    {
        $path = trim($path);
        if ($path === '') {
            return '/';
        }
        if (!str_starts_with($path, '/')) {
            $path = '/' . $path;
        }
        if (!str_ends_with($path, '/')) {
            $path .= '/';
        }
        $path = preg_replace('#/+#', '/', $path) ?? $path;
        return $path;
    }
}
