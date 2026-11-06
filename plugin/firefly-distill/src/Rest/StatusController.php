<?php

namespace FireflyDistill\Rest;

use FireflyDistill\Hosts\HostRepository;
use FireflyDistill\Hosts\HostsMapWriter;
use WP_REST_Request;
use WP_REST_Response;

final class StatusController
{
    public static function show(WP_REST_Request $request): WP_REST_Response
    {
        global $wpdb;
        $settings = get_option('ffd_settings', []);
        $hosts = array_map(static fn ($h) => $h->toArray(), (new HostRepository())->all());
        $pending = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}ffd_queue WHERE status = 'pending'");
        return new WP_REST_Response([
            'version' => FFD_VERSION,
            'schema' => FFD_SCHEMA_VERSION,
            'serve_enabled' => (int) ($settings['serve_enabled'] ?? 0),
            'cloud_url' => (string) ($settings['cloud_url'] ?? ''),
            'site_key_id' => (string) ($settings['site_key_id'] ?? ''),
            'openseo_project_id' => (string) ($settings['openseo_project_id'] ?? ''),
            'queue_pending' => $pending,
            'reload_pending' => (new HostsMapWriter())->pendingReload(),
            'hosts' => $hosts,
        ]);
    }
}
