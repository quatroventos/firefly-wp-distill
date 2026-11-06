<?php

namespace FireflyDistill\Admin;

use FireflyDistill\Hosts\HostRepository;
use FireflyDistill\Queue\Enqueuer;

final class JobsPage
{
    public static function render(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die('Sem permissão');
        }
        global $wpdb;
        $rows = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}ffd_queue ORDER BY id DESC LIMIT 100", ARRAY_A) ?: [];
        echo '<div class="wrap ffd-wrap"><h1>Fila / jobs</h1>';
        echo '<h2>Rebuild</h2><form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
        wp_nonce_field('ffd_rebuild_host');
        echo '<input type="hidden" name="action" value="ffd_rebuild_host" /><select name="host_id">';
        foreach ((new HostRepository())->all() as $host) {
            echo '<option value="' . (int) $host->id . '">' . esc_html($host->host) . '</option>';
        }
        echo '</select> ';
        submit_button('Rebuild host', 'primary', '', false);
        echo '</form>';
        echo '<table class="widefat"><thead><tr><th>ID</th><th>Host</th><th>Path</th><th>Reason</th><th>Status</th><th>Job</th></tr></thead><tbody>';
        foreach ($rows as $row) {
            echo '<tr><td>' . (int) $row['id'] . '</td><td>' . esc_html((string) $row['host']) . '</td><td>' . esc_html((string) $row['path']) . '</td><td>' . esc_html((string) $row['reason']) . '</td><td>' . esc_html((string) $row['status']) . '</td><td>' . esc_html((string) $row['cloud_job_id']) . '</td></tr>';
        }
        echo '</tbody></table></div>';
    }

    public static function rebuild(): void
    {
        check_admin_referer('ffd_rebuild_host');
        if (!current_user_can('manage_options')) {
            wp_die('Sem permissão');
        }
        $host = (new HostRepository())->find((int) ($_POST['host_id'] ?? 0));
        if ($host) {
            $scheme = $host->https ? 'https' : 'http';
            (new Enqueuer())->enqueueHost($host->host, '/', $scheme . '://' . $host->host . '/', $host->post_id, 'manual');
            Enqueuer::flushHost($host->host);
        }
        wp_safe_redirect(admin_url('admin.php?page=firefly-distill-jobs'));
        exit;
    }
}
