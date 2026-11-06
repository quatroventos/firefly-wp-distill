<?php

namespace FireflyDistill\Serving;

use FireflyDistill\Hosts\HostValidator;

final class StoragePaths
{
    public static function root(): string
    {
        $upload = wp_upload_dir();
        return trailingslashit($upload['basedir']) . 'firefly-distill';
    }

    public static function ensureTree(): void
    {
        $root = self::root();
        wp_mkdir_p($root . '/builds');
        wp_mkdir_p($root . '/current');
        $ht = $root . '/.htaccess';
        if (!file_exists($ht)) {
            file_put_contents($ht, "Options -Indexes\n<FilesMatch \"\\.(php|phtml)$\">\n  Require all denied\n</FilesMatch>\n");
        }
        $index = $root . '/index.html';
        if (!file_exists($index)) {
            file_put_contents($index, '');
        }
    }

    public static function mapFile(): string
    {
        return self::root() . '/hosts.map.conf';
    }

    public static function buildsHost(string $host): string
    {
        return self::root() . '/builds/' . $host;
    }

    public static function buildDir(string $host, string $buildId): string
    {
        return self::buildsHost($host) . '/' . $buildId;
    }

    public static function currentLink(string $host): string
    {
        return self::root() . '/current/' . $host;
    }

    public static function assertHost(string $host): string
    {
        $host = HostValidator::normalize($host);
        if (!HostValidator::isValid($host)) {
            throw new \InvalidArgumentException('host inválido');
        }
        return $host;
    }
}
