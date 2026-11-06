<?php

namespace FireflyDistill\Rest;

use WP_REST_Request;
use WP_REST_Response;

final class SuggestionsController
{
    public static function index(WP_REST_Request $request): WP_REST_Response
    {
        global $wpdb;
        $where = ['1=1'];
        $args = [];
        foreach (['host', 'category', 'severity', 'status'] as $field) {
            $val = $request->get_param($field);
            if (is_string($val) && $val !== '') {
                $where[] = "{$field} = %s";
                $args[] = sanitize_text_field($val);
            }
        }
        $sql = 'SELECT * FROM ' . $wpdb->prefix . 'ffd_suggestions WHERE ' . implode(' AND ', $where) . ' ORDER BY updated_at DESC LIMIT 500';
        $rows = $args === []
            ? $wpdb->get_results($sql, ARRAY_A)
            : $wpdb->get_results($wpdb->prepare($sql, ...$args), ARRAY_A);
        return new WP_REST_Response(['suggestions' => $rows ?: []]);
    }

    public static function store(WP_REST_Request $request): WP_REST_Response
    {
        $items = $request->get_param('suggestions');
        if (!is_array($items)) {
            return new WP_REST_Response(['message' => 'suggestions[] obrigatório'], 422);
        }
        global $wpdb;
        $table = $wpdb->prefix . 'ffd_suggestions';
        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }
            $cloudId = sanitize_text_field((string) ($item['cloud_suggestion_id'] ?? $item['id'] ?? ''));
            if ($cloudId === '') {
                continue;
            }
            $row = [
                'cloud_suggestion_id' => $cloudId,
                'host' => sanitize_text_field((string) ($item['host'] ?? '')),
                'url' => esc_url_raw((string) ($item['url'] ?? '')),
                'category' => sanitize_key((string) ($item['category'] ?? 'content_seo_geo')),
                'severity' => sanitize_key((string) ($item['severity'] ?? 'medium')),
                'title' => sanitize_text_field((string) ($item['title'] ?? '')),
                'body' => wp_kses_post((string) ($item['body'] ?? '')),
                'status' => sanitize_key((string) ($item['status'] ?? 'open')),
                'source' => sanitize_key((string) ($item['source'] ?? 'agent')),
                'updated_at' => current_time('mysql', true),
            ];
            $exists = $wpdb->get_var($wpdb->prepare("SELECT id FROM {$table} WHERE cloud_suggestion_id = %s", $cloudId));
            if ($exists) {
                $wpdb->update($table, $row, ['id' => (int) $exists]);
            } else {
                $wpdb->insert($table, $row);
            }
        }
        return new WP_REST_Response(['ok' => true]);
    }

    public static function update(WP_REST_Request $request): WP_REST_Response
    {
        $id = (int) $request['id'];
        $status = sanitize_key((string) $request->get_param('status'));
        $allowed = ['open', 'accepted', 'rejected', 'applied', 'deferred'];
        if (!in_array($status, $allowed, true)) {
            return new WP_REST_Response(['message' => 'status inválido'], 422);
        }
        global $wpdb;
        $wpdb->update($wpdb->prefix . 'ffd_suggestions', [
            'status' => $status,
            'updated_at' => current_time('mysql', true),
        ], ['id' => $id]);
        return new WP_REST_Response(['ok' => true, 'status' => $status]);
    }
}
