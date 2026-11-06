<?php

namespace FireflyDistill\Admin;

use FireflyDistill\Queue\CloudClient;
use FireflyDistill\Security\Hmac;

final class ConnectionPage
{
    public static function render(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die('Sem permissão');
        }
        $settings = get_option('ffd_settings', []);
        $secretSet = Hmac::secret() !== '';
        echo '<div class="wrap ffd-wrap"><h1>Conexão</h1>';
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
        wp_nonce_field('ffd_save_connection');
        echo '<input type="hidden" name="action" value="ffd_save_connection" />';
        echo '<table class="form-table"><tr><th>Cloud URL</th><td><input class="regular-text" name="cloud_url" value="' . esc_attr((string) ($settings['cloud_url'] ?? '')) . '" placeholder="https://distill.exemplo.com" /></td></tr>';
        echo '<tr><th>Site key</th><td><input class="regular-text" name="site_key_id" value="' . esc_attr((string) ($settings['site_key_id'] ?? '')) . '" /></td></tr>';
        echo '<tr><th>HMAC secret</th><td>' . ($secretSet ? 'configurado (mascarado). Use FFD_HMAC_SECRET no wp-config.php.' : 'não configurado') . '</td></tr>';
        echo '<tr><th>OpenSEO project</th><td><input class="regular-text" name="openseo_project_id" value="' . esc_attr((string) ($settings['openseo_project_id'] ?? '')) . '" /></td></tr>';
        echo '<tr><th>Retenção de builds</th><td><input type="number" min="1" name="retention_builds" value="' . (int) ($settings['retention_builds'] ?? 5) . '" /></td></tr>';
        echo '</table>';
        submit_button('Salvar');
        echo '</form>';
        $health = (new CloudClient())->health();
        echo '<h2>Teste</h2><pre>' . esc_html(wp_json_encode($health, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)) . '</pre></div>';
    }

    public static function save(): void
    {
        check_admin_referer('ffd_save_connection');
        if (!current_user_can('manage_options')) {
            wp_die('Sem permissão');
        }
        $settings = get_option('ffd_settings', []);
        if (!is_array($settings)) {
            $settings = [];
        }
        $settings['cloud_url'] = esc_url_raw((string) ($_POST['cloud_url'] ?? ''));
        $settings['site_key_id'] = sanitize_text_field((string) ($_POST['site_key_id'] ?? ''));
        $settings['openseo_project_id'] = sanitize_text_field((string) ($_POST['openseo_project_id'] ?? ''));
        $settings['retention_builds'] = max(1, (int) ($_POST['retention_builds'] ?? 5));
        update_option('ffd_settings', $settings, false);
        wp_safe_redirect(admin_url('admin.php?page=firefly-distill-connection'));
        exit;
    }
}
