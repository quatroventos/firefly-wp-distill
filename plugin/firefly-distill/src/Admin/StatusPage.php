<?php

namespace FireflyDistill\Admin;

use FireflyDistill\Hosts\HostRepository;
use FireflyDistill\Hosts\HostsMapWriter;

final class StatusPage
{
    public static function render(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die('Sem permissão');
        }
        $settings = get_option('ffd_settings', []);
        $hosts = (new HostRepository())->all();
        global $wpdb;
        $pending = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}ffd_queue WHERE status = 'pending'");
        $last = get_option('ffd_last_webhook', []);
        echo '<div class="wrap ffd-wrap"><h1>Status</h1>';
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
        wp_nonce_field('ffd_save_serve');
        echo '<input type="hidden" name="action" value="ffd_save_serve" />';
        echo '<p><label><input type="checkbox" name="serve_enabled" value="1" ' . checked((int) ($settings['serve_enabled'] ?? 0), 1, false) . ' /> Serve estático global (ainda depende de static_enabled por host e do reload Nginx)</label></p>';
        submit_button('Salvar');
        echo '</form>';
        if ((new HostsMapWriter())->pendingReload()) {
            echo '<div class="notice notice-warning"><p>Reload Nginx pendente.</p></div>';
        }
        echo '<p>Fila pendente: <strong>' . $pending . '</strong></p>';
        echo '<p>OpenSEO do site: <code>' . esc_html((string) ($settings['openseo_project_id'] ?? '')) . '</code></p>';
        if (is_array($last) && $last !== []) {
            echo '<p>Último webhook: ' . esc_html((string) ($last['status'] ?? '')) . ' @ ' . esc_html((string) ($last['at'] ?? '')) . ' (' . esc_html((string) ($last['host'] ?? '')) . ')</p>';
        }
        echo '<table class="widefat"><thead><tr><th>Host</th><th>Estático</th><th>Build current</th></tr></thead><tbody>';
        foreach ($hosts as $host) {
            echo '<tr><td><code>' . esc_html($host->host) . '</code></td><td>' . ((int) $host->static_enabled ? 'on' : 'off') . '</td><td>' . esc_html((string) $host->current_build_id) . '</td></tr>';
        }
        echo '</tbody></table></div>';
    }

    public static function saveServe(): void
    {
        check_admin_referer('ffd_save_serve');
        if (!current_user_can('manage_options')) {
            wp_die('Sem permissão');
        }
        $settings = get_option('ffd_settings', []);
        if (!is_array($settings)) {
            $settings = [];
        }
        $settings['serve_enabled'] = empty($_POST['serve_enabled']) ? 0 : 1;
        update_option('ffd_settings', $settings, false);
        (new HostsMapWriter())->write();
        wp_safe_redirect(admin_url('admin.php?page=firefly-distill'));
        exit;
    }
}
