<?php

namespace FireflyDistill\Admin;

final class Menu
{
    public function register(): void
    {
        add_action('admin_menu', [$this, 'menu']);
        add_action('admin_enqueue_scripts', [$this, 'assets']);
        add_action('admin_post_ffd_save_host', [HostsMappingPage::class, 'save']);
        add_action('admin_post_ffd_delete_host', [HostsMappingPage::class, 'delete']);
        add_action('admin_post_ffd_import_hosts', [HostsMappingPage::class, 'import']);
        add_action('admin_post_ffd_save_connection', [ConnectionPage::class, 'save']);
        add_action('admin_post_ffd_save_exclusions', [ExclusionsPage::class, 'save']);
        add_action('admin_post_ffd_save_serve', [StatusPage::class, 'saveServe']);
        add_action('admin_post_ffd_suggestion_status', [SuggestionsPage::class, 'saveStatus']);
        add_action('admin_post_ffd_rebuild_host', [JobsPage::class, 'rebuild']);
        add_action('admin_post_ffd_activate_build', [BuildsPage::class, 'activate']);
        add_action('admin_post_ffd_rollback_host', [BuildsPage::class, 'rollback']);
    }

    public function menu(): void
    {
        $cap = 'manage_options';
        add_menu_page(
            'firefly-distill',
            'firefly-distill',
            $cap,
            'firefly-distill',
            [StatusPage::class, 'render'],
            'dashicons-performance',
            58
        );
        add_submenu_page('firefly-distill', 'Status', 'Status', $cap, 'firefly-distill', [StatusPage::class, 'render']);
        add_submenu_page('firefly-distill', 'Hosts / Mapeamento', 'Hosts / Mapeamento', $cap, 'firefly-distill-hosts', [HostsMappingPage::class, 'render']);
        add_submenu_page('firefly-distill', 'Dashboard', 'Dashboard', $cap, 'firefly-distill-dashboard', [DashboardPage::class, 'render']);
        add_submenu_page('firefly-distill', 'URL', 'URL', $cap, 'firefly-distill-url', [UrlDetailPage::class, 'render']);
        add_submenu_page('firefly-distill', 'Sugestões', 'Sugestões', $cap, 'firefly-distill-suggestions', [SuggestionsPage::class, 'render']);
        add_submenu_page('firefly-distill', 'Fila / jobs', 'Fila / jobs', $cap, 'firefly-distill-jobs', [JobsPage::class, 'render']);
        add_submenu_page('firefly-distill', 'Builds', 'Builds', $cap, 'firefly-distill-builds', [BuildsPage::class, 'render']);
        add_submenu_page('firefly-distill', 'Exclusões', 'Exclusões', $cap, 'firefly-distill-exclusions', [ExclusionsPage::class, 'render']);
        add_submenu_page('firefly-distill', 'Conexão', 'Conexão', $cap, 'firefly-distill-connection', [ConnectionPage::class, 'render']);
        add_submenu_page('firefly-distill', 'Ajuda', 'Ajuda', $cap, 'firefly-distill-help', [HelpPage::class, 'render']);
    }

    public function assets(string $hook): void
    {
        if (!str_contains($hook, 'firefly-distill')) {
            return;
        }
        wp_enqueue_style('ffd-admin', FFD_URL . 'src/Admin/assets/admin.css', [], FFD_VERSION);
        wp_enqueue_script('ffd-admin', FFD_URL . 'src/Admin/assets/admin.js', [], FFD_VERSION, true);
        wp_localize_script('ffd-admin', 'ffdAdmin', [
            'rest' => esc_url_raw(rest_url(FFD_REST_NS)),
            'nonce' => wp_create_nonce('wp_rest'),
        ]);
    }
}
