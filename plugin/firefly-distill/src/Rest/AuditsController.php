<?php

namespace FireflyDistill\Rest;

use WP_REST_Request;
use WP_REST_Response;

final class AuditsController
{
    public static function index(WP_REST_Request $request): WP_REST_Response
    {
        global $wpdb;
        $host = sanitize_text_field((string) ($request->get_param('host') ?? ''));
        $sql = "SELECT * FROM {$wpdb->prefix}ffd_page_audits";
        $args = [];
        if ($host !== '') {
            $sql .= ' WHERE host = %s';
            $args[] = $host;
        }
        $sql .= ' ORDER BY audited_at DESC LIMIT 500';
        $rows = $args === []
            ? $wpdb->get_results($sql, ARRAY_A)
            : $wpdb->get_results($wpdb->prepare($sql, ...$args), ARRAY_A);
        return new WP_REST_Response(['audits' => $rows ?: []]);
    }

    public static function detail(WP_REST_Request $request): WP_REST_Response
    {
        global $wpdb;
        $url = (string) $request->get_param('url');
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}ffd_page_audits WHERE url = %s ORDER BY audited_at DESC",
            $url
        ), ARRAY_A) ?: [];
        return new WP_REST_Response(['audits' => $rows]);
    }
}
