<?php

namespace FireflyDistill\Rest;

use FireflyDistill\Hosts\HostsMapWriter;
use WP_REST_Request;
use WP_REST_Response;

final class ServeController
{
    public static function update(WP_REST_Request $request): WP_REST_Response
    {
        $settings = get_option('ffd_settings', []);
        if (!is_array($settings)) {
            $settings = [];
        }
        $settings['serve_enabled'] = (int) $request->get_param('enabled') ? 1 : 0;
        update_option('ffd_settings', $settings, false);
        (new HostsMapWriter())->write();
        return new WP_REST_Response(['serve_enabled' => $settings['serve_enabled']]);
    }
}
