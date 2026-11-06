<?php

namespace FireflyDistill\Serving;

final class Exclusions
{
    /**
     * @return string[]
     */
    public static function defaults(): array
    {
        return [
            '/wp-admin',
            '/wp-json',
            '/wp-login.php',
            '/xmlrpc.php',
            '/wp-cron.php',
        ];
    }

    /**
     * @return string[]
     */
    public static function all(): array
    {
        $settings = get_option('ffd_settings', []);
        $extra = is_array($settings) && isset($settings['exclusions']) && is_array($settings['exclusions'])
            ? $settings['exclusions']
            : [];
        $paths = array_merge(self::defaults(), $extra);
        $paths = array_map(static fn ($p) => '/' . ltrim((string) $p, '/'), $paths);
        return array_values(array_unique($paths));
    }

    public static function matches(string $path): bool
    {
        $path = '/' . ltrim($path, '/');
        foreach (self::all() as $ex) {
            if ($path === $ex || str_starts_with($path, rtrim($ex, '/') . '/')) {
                return true;
            }
        }
        return false;
    }
}
