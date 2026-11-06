<?php

namespace FireflyDistill\Admin;

use FireflyDistill\Serving\Publisher;

final class BuildsPage
{
    public static function render(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die('Sem permissão');
        }
        global $wpdb;
        $rows = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}ffd_builds ORDER BY created_at DESC LIMIT 100", ARRAY_A) ?: [];
        echo '<div class="wrap ffd-wrap"><h1>Builds</h1><table class="widefat"><thead><tr><th>Host</th><th>Build</th><th>Current</th><th>Páginas</th><th></th></tr></thead><tbody>';
        foreach ($rows as $row) {
            echo '<tr><td>' . esc_html((string) $row['host']) . '</td><td><code>' . esc_html((string) $row['build_id']) . '</code></td><td>' . ((int) $row['is_current'] ? 'sim' : '') . '</td><td>' . (int) $row['page_count'] . '</td><td>';
            echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" style="display:inline">';
            wp_nonce_field('ffd_activate_build');
            echo '<input type="hidden" name="action" value="ffd_activate_build" />';
            echo '<input type="hidden" name="host" value="' . esc_attr((string) $row['host']) . '" />';
            echo '<input type="hidden" name="build_id" value="' . esc_attr((string) $row['build_id']) . '" />';
            submit_button('Ativar', 'secondary', '', false);
            echo '</form> ';
            echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" style="display:inline">';
            wp_nonce_field('ffd_rollback_host');
            echo '<input type="hidden" name="action" value="ffd_rollback_host" />';
            echo '<input type="hidden" name="host" value="' . esc_attr((string) $row['host']) . '" />';
            submit_button('Rollback', 'secondary', '', false);
            echo '</form></td></tr>';
        }
        echo '</tbody></table></div>';
    }

    public static function activate(): void
    {
        check_admin_referer('ffd_activate_build');
        if (!current_user_can('manage_options')) {
            wp_die('Sem permissão');
        }
        (new Publisher())->activate((string) $_POST['host'], (string) $_POST['build_id']);
        wp_safe_redirect(admin_url('admin.php?page=firefly-distill-builds'));
        exit;
    }

    public static function rollback(): void
    {
        check_admin_referer('ffd_rollback_host');
        if (!current_user_can('manage_options')) {
            wp_die('Sem permissão');
        }
        (new Publisher())->rollback((string) $_POST['host']);
        wp_safe_redirect(admin_url('admin.php?page=firefly-distill-builds'));
        exit;
    }
}
