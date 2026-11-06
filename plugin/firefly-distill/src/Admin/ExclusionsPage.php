<?php

namespace FireflyDistill\Admin;

use FireflyDistill\Serving\Exclusions;

final class ExclusionsPage
{
    public static function render(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die('Sem permissão');
        }
        $settings = get_option('ffd_settings', []);
        $extra = is_array($settings['exclusions'] ?? null) ? implode("\n", $settings['exclusions']) : '';
        echo '<div class="wrap ffd-wrap"><h1>Exclusões</h1>';
        echo '<p>Paths que nunca são estáticos. Padrão: ' . esc_html(implode(', ', Exclusions::defaults())) . '</p>';
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
        wp_nonce_field('ffd_save_exclusions');
        echo '<input type="hidden" name="action" value="ffd_save_exclusions" />';
        echo '<textarea name="exclusions" class="large-text code" rows="8">' . esc_textarea($extra) . '</textarea>';
        submit_button('Salvar');
        echo '</form></div>';
    }

    public static function save(): void
    {
        check_admin_referer('ffd_save_exclusions');
        if (!current_user_can('manage_options')) {
            wp_die('Sem permissão');
        }
        $raw = (string) ($_POST['exclusions'] ?? '');
        $lines = array_values(array_filter(array_map('trim', preg_split("/\r\n|\n|\r/", $raw) ?: [])));
        $settings = get_option('ffd_settings', []);
        if (!is_array($settings)) {
            $settings = [];
        }
        $settings['exclusions'] = $lines;
        update_option('ffd_settings', $settings, false);
        wp_safe_redirect(admin_url('admin.php?page=firefly-distill-exclusions'));
        exit;
    }
}
