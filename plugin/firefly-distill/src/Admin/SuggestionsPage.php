<?php

namespace FireflyDistill\Admin;

final class SuggestionsPage
{
    public static function render(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die('Sem permissão');
        }
        global $wpdb;
        $host = sanitize_text_field((string) ($_GET['host'] ?? ''));
        $status = sanitize_text_field((string) ($_GET['status'] ?? ''));
        $where = ['1=1'];
        $args = [];
        if ($host !== '') {
            $where[] = 'host = %s';
            $args[] = $host;
        }
        if ($status !== '') {
            $where[] = 'status = %s';
            $args[] = $status;
        }
        $sql = 'SELECT * FROM ' . $wpdb->prefix . 'ffd_suggestions WHERE ' . implode(' AND ', $where) . ' ORDER BY updated_at DESC LIMIT 200';
        $rows = $args ? $wpdb->get_results($wpdb->prepare($sql, ...$args), ARRAY_A) : $wpdb->get_results($sql, ARRAY_A);
        $rows = $rows ?: [];

        echo '<div class="wrap ffd-wrap"><h1>Sugestões</h1>';
        echo '<form method="get"><input type="hidden" name="page" value="firefly-distill-suggestions" />';
        echo '<input name="host" placeholder="host" value="' . esc_attr($host) . '" /> ';
        echo '<select name="status"><option value="">status</option>';
        foreach (['open', 'accepted', 'rejected', 'deferred'] as $st) {
            echo '<option ' . selected($status, $st, false) . '>' . esc_html($st) . '</option>';
        }
        echo '</select> ';
        submit_button('Filtrar', 'secondary', '', false);
        echo '</form><table class="widefat"><thead><tr><th>Host</th><th>URL</th><th>Título</th><th>Sev</th><th>Status</th><th></th></tr></thead><tbody>';
        foreach ($rows as $row) {
            echo '<tr><td>' . esc_html((string) $row['host']) . '</td><td>' . esc_html((string) $row['url']) . '</td><td>' . esc_html((string) $row['title']) . '</td><td>' . esc_html((string) $row['severity']) . '</td><td>' . esc_html((string) $row['status']) . '</td><td>';
            echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" style="display:inline">';
            wp_nonce_field('ffd_suggestion_status');
            echo '<input type="hidden" name="action" value="ffd_suggestion_status" />';
            echo '<input type="hidden" name="id" value="' . (int) $row['id'] . '" />';
            echo '<select name="status">';
            foreach (['accepted', 'rejected', 'deferred', 'open'] as $st) {
                echo '<option value="' . esc_attr($st) . '">' . esc_html($st) . '</option>';
            }
            echo '</select> ';
            submit_button('Aplicar status', 'secondary', '', false);
            echo '</form></td></tr>';
        }
        echo '</tbody></table></div>';
    }

    public static function saveStatus(): void
    {
        check_admin_referer('ffd_suggestion_status');
        if (!current_user_can('manage_options')) {
            wp_die('Sem permissão');
        }
        $request = new \WP_REST_Request('POST', '/');
        $request['id'] = (int) ($_POST['id'] ?? 0);
        $request->set_param('status', (string) ($_POST['status'] ?? 'open'));
        \FireflyDistill\Rest\SuggestionsController::update($request);
        wp_safe_redirect(admin_url('admin.php?page=firefly-distill-suggestions'));
        exit;
    }
}
