<?php

namespace FireflyDistill\Rest;

use WP_REST_Request;
use WP_REST_Response;

final class WebhookController
{
    public static function handle(WP_REST_Request $request): WP_REST_Response
    {
        $event = sanitize_key((string) $request->get_param('event'));
        $jobId = (string) $request->get_param('job_id');
        $host = sanitize_text_field((string) $request->get_param('host'));
        $status = sanitize_key((string) $request->get_param('status'));

        global $wpdb;
        if ($jobId !== '') {
            $wpdb->update($wpdb->prefix . 'ffd_queue', [
                'status' => in_array($status, ['completed', 'completed_with_warnings'], true) ? 'done' : ($status === 'failed' ? 'error' : 'sent'),
                'processed_at' => current_time('mysql', true),
            ], ['cloud_job_id' => $jobId]);
        }

        $audits = $request->get_param('audits');
        if (is_array($audits)) {
            $table = $wpdb->prefix . 'ffd_page_audits';
            foreach ($audits as $audit) {
                if (!is_array($audit)) {
                    continue;
                }
                $wpdb->insert($table, [
                    'host' => sanitize_text_field((string) ($audit['host'] ?? $host)),
                    'path' => sanitize_text_field((string) ($audit['path'] ?? '/')),
                    'url' => esc_url_raw((string) ($audit['url'] ?? '')),
                    'phase' => sanitize_key((string) ($audit['phase'] ?? 'build')),
                    'performance' => isset($audit['performance']) ? (int) $audit['performance'] : null,
                    'seo' => isset($audit['seo']) ? (int) $audit['seo'] : null,
                    'accessibility' => isset($audit['accessibility']) ? (int) $audit['accessibility'] : null,
                    'best_practices' => isset($audit['best_practices']) ? (int) $audit['best_practices'] : null,
                    'psi_json' => isset($audit['psi_json']) ? wp_json_encode($audit['psi_json']) : null,
                    'openseo_slice' => isset($audit['openseo_slice']) ? wp_json_encode($audit['openseo_slice']) : null,
                    'audited_at' => current_time('mysql', true),
                ]);
            }
        }

        $suggestions = $request->get_param('suggestions');
        if (is_array($suggestions)) {
            $clone = clone $request;
            $clone->set_param('suggestions', $suggestions);
            SuggestionsController::store($clone);
        }

        update_option('ffd_last_webhook', [
            'event' => $event,
            'job_id' => $jobId,
            'host' => $host,
            'status' => $status,
            'at' => current_time('mysql', true),
        ], false);

        return new WP_REST_Response(['ok' => true]);
    }
}
