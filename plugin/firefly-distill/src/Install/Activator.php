<?php

namespace FireflyDistill\Install;

use FireflyDistill\Hosts\HostRepository;
use FireflyDistill\Hosts\HostsMapWriter;
use FireflyDistill\Hosts\HostValidator;
use FireflyDistill\Serving\StoragePaths;

final class Activator
{
    public static function activate(): void
    {
        Schema::install();
        self::seedSettings();
        self::seedPrimaryHost();
        StoragePaths::ensureTree();
        (new HostsMapWriter())->write();
    }

    public static function deactivate(): void
    {
        wp_clear_scheduled_hook('ffd_flush_host_queue');
    }

    private static function seedSettings(): void
    {
        $current = get_option('ffd_settings', null);
        if (is_array($current)) {
            return;
        }
        add_option('ffd_settings', [
            'cloud_url' => '',
            'site_key_id' => '',
            'serve_enabled' => 0,
            'retention_builds' => 5,
            'exclusions' => [],
            'openseo_project_id' => '',
        ], '', false);
    }

    private static function seedPrimaryHost(): void
    {
        $home = home_url('/');
        $host = HostValidator::normalize((string) wp_parse_url($home, PHP_URL_HOST));
        if (!HostValidator::isValid($host)) {
            return;
        }

        $repo = new HostRepository();
        if ($repo->findByHost($host) !== null) {
            return;
        }

        $frontId = (int) get_option('page_on_front');
        $repo->insert([
            'host' => $host,
            'post_id' => $frontId,
            'https' => wp_parse_url($home, PHP_URL_SCHEME) === 'https' ? 1 : 0,
            'flag_robots_index' => 1,
            'flag_sitemap' => 1,
            'flag_llm_txt' => 1,
            'static_enabled' => 0,
            'is_primary' => 1,
        ]);
    }
}
