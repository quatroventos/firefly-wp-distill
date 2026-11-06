<?php

namespace FireflyDistill\Security;

use FireflyDistill\Hosts\HostValidator;

final class PathGuard
{
    private const BLOCKED_EXTENSIONS = [
        'php', 'phtml', 'php3', 'php4', 'php5', 'phar', 'cgi', 'exe', 'sh', 'htaccess',
    ];

    private const ALLOWED_EXTENSIONS = [
        'html', 'htm', 'css', 'js', 'map', 'json', 'txt', 'xml', 'svg',
        'png', 'jpg', 'jpeg', 'gif', 'webp', 'avif', 'ico',
        'woff', 'woff2', 'ttf', 'otf',
    ];

    public static function isSafeRelative(string $relpath): bool
    {
        $relpath = str_replace('\\', '/', $relpath);
        if ($relpath === '' || str_starts_with($relpath, '/') || str_contains($relpath, '..')) {
            return false;
        }
        if (str_contains($relpath, "\0")) {
            return false;
        }
        $base = strtolower(basename($relpath));
        if ($base === '.htaccess' || $base === '.htpasswd' || str_starts_with($base, '.')) {
            return false;
        }
        $ext = strtolower((string) pathinfo($relpath, PATHINFO_EXTENSION));
        if ($ext === '') {
            return false;
        }
        if (in_array($ext, self::BLOCKED_EXTENSIONS, true)) {
            return false;
        }
        return in_array($ext, self::ALLOWED_EXTENSIONS, true);
    }

    public static function isMappedHost(string $host, callable $exists): bool
    {
        if (!HostValidator::isValid($host)) {
            return false;
        }
        return (bool) $exists($host);
    }

    public static function resolveUnder(string $root, string $relpath): ?string
    {
        if (!self::isSafeRelative($relpath)) {
            return null;
        }
        $rootReal = realpath($root);
        if ($rootReal === false) {
            return null;
        }
        $target = $rootReal . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relpath);
        $parent = dirname($target);
        if (!is_dir($parent)) {
            return $target;
        }
        $parentReal = realpath($parent);
        if ($parentReal === false || !str_starts_with($parentReal, $rootReal)) {
            return null;
        }
        return $target;
    }
}
