<?php

namespace FireflyDistill\Admin;

use FireflyDistill\Hosts\HostImporter;
use FireflyDistill\Hosts\HostRepository;
use FireflyDistill\Hosts\HostsMapWriter;
use FireflyDistill\Serving\SnippetGenerator;

final class HostsMappingPage
{
    public static function render(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die('Sem permissão');
        }
        $repo = new HostRepository();
        $editId = isset($_GET['host_id']) ? (int) $_GET['host_id'] : 0;
        $editing = $editId ? $repo->find($editId) : null;
        echo '<div class="wrap ffd-wrap"><h1>Hosts / Mapeamento</h1>';
        if ((new HostsMapWriter())->pendingReload()) {
            echo '<div class="notice notice-warning"><p>hosts.map.conf foi regravado. Reload Nginx pendente (<code>nginx -t && nginx -s reload</code>). O plugin não executa esse comando.</p></div>';
        }
        if (isset($_GET['ffd_notice'])) {
            echo '<div class="notice notice-success"><p>' . esc_html((string) $_GET['ffd_notice']) . '</p></div>';
        }
        $table = new HostsListTable();
        $table->prepare_items();
        $table->display();

        self::form($editing);
        self::importBox();
        self::snippets();
        echo '</div>';
    }

    private static function form(?\FireflyDistill\Model\Host $editing): void
    {
        $action = admin_url('admin-post.php');
        $h = $editing;
        echo '<h2>' . ($h ? 'Editar host' : 'Adicionar host') . '</h2>';
        echo '<form method="post" action="' . esc_url($action) . '" class="ffd-form">';
        wp_nonce_field('ffd_save_host');
        echo '<input type="hidden" name="action" value="ffd_save_host" />';
        if ($h) {
            echo '<input type="hidden" name="id" value="' . (int) $h->id . '" />';
        }
        echo '<table class="form-table"><tbody>';
        self::row('Host público', '<input class="regular-text" name="host" required value="' . esc_attr($h->host ?? '') . '" placeholder="lp.exemplo.com.br" />');
        self::row('HTTPS', '<label><input type="checkbox" name="https" value="1" ' . checked($h->https ?? 1, 1, false) . ' /> usar https</label>');
        self::row('Página / post raiz', '<input class="regular-text ffd-post-search" name="post_title" value="' . esc_attr($h && $h->post_id ? get_the_title($h->post_id) : '') . '" placeholder="buscar por título" /><input type="hidden" name="post_id" value="' . (int) ($h->post_id ?? 0) . '" /><div class="ffd-post-results"></div>');
        $routes = $h->routes ?? [];
        $routeHtml = '<div class="ffd-routes">';
        if ($routes === []) {
            $routeHtml .= self::routeRow(null);
        } else {
            foreach ($routes as $route) {
                $routeHtml .= self::routeRow($route);
            }
        }
        $routeHtml .= '</div><p><button type="button" class="button ffd-add-route">Adicionar rota</button></p>';
        self::row('Rotas extras', $routeHtml);
        self::row('Flags',
            '<label><input type="checkbox" name="flag_robots_index" value="1" ' . checked($h->flag_robots_index ?? 1, 1, false) . ' /> robots index</label><br>' .
            '<label><input type="checkbox" name="flag_sitemap" value="1" ' . checked($h->flag_sitemap ?? 1, 1, false) . ' /> sitemap.xml</label><br>' .
            '<label><input type="checkbox" name="flag_llm_txt" value="1" ' . checked($h->flag_llm_txt ?? 1, 1, false) . ' /> llm.txt</label><br>' .
            '<label><input type="checkbox" name="static_enabled" value="1" ' . checked($h->static_enabled ?? 0, 1, false) . ' /> estático ligado (entra no hosts.map.conf)</label>'
        );
        self::row('OpenSEO', '<input class="regular-text" name="openseo_project_id" value="' . esc_attr($h->openseo_project_id ?? '') . '" placeholder="herda do site se vazio" />');
        self::row('GSC property', '<input class="regular-text" name="gsc_property" value="' . esc_attr($h->gsc_property ?? '') . '" placeholder="sc-domain:lp.exemplo.com.br" />');
        echo '</tbody></table>';
        submit_button($h ? 'Salvar host' : 'Cadastrar host');
        echo '</form>';
        if ($h) {
            echo '<form method="post" action="' . esc_url($action) . '" onsubmit="return confirm(\'Apagar este host?\');">';
            wp_nonce_field('ffd_delete_host');
            echo '<input type="hidden" name="action" value="ffd_delete_host" /><input type="hidden" name="id" value="' . (int) $h->id . '" />';
            submit_button('Apagar host', 'delete');
            echo '</form>';
        }
    }

    private static function routeRow($route): string
    {
        $path = is_object($route) ? $route->path : '';
        $postId = is_object($route) ? (int) $route->post_id : 0;
        $title = $postId ? get_the_title($postId) : '';
        $in = is_object($route) ? (int) $route->in_sitemap : 1;
        return '<div class="ffd-route">' .
            '<input name="route_path[]" placeholder="/obrigado/" value="' . esc_attr($path) . '" />' .
            '<input class="ffd-post-search" name="route_post_title[]" placeholder="buscar post" value="' . esc_attr($title) . '" />' .
            '<input type="hidden" name="route_post_id[]" value="' . $postId . '" />' .
            '<label><input type="checkbox" name="route_in_sitemap[]" value="1" ' . checked($in, 1, false) . ' /> sitemap</label>' .
            '<div class="ffd-post-results"></div></div>';
    }

    private static function importBox(): void
    {
        echo '<h2>Import CSV</h2><p>Colunas: <code>host,post_id[,path][,https][,robots][,sitemap][,llm][,static]</code></p>';
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
        wp_nonce_field('ffd_import_hosts');
        echo '<input type="hidden" name="action" value="ffd_import_hosts" />';
        echo '<textarea name="csv" class="large-text code" rows="6"></textarea>';
        echo '<p><label><input type="checkbox" name="dry_run" value="1" checked /> dry-run</label></p>';
        submit_button('Importar');
        echo '</form>';
        if (!empty($_GET['ffd_import'])) {
            $raw = get_transient('ffd_import_preview');
            if (is_array($raw)) {
                echo '<table class="widefat"><thead><tr><th>Linha</th><th>OK</th><th>Erros</th></tr></thead><tbody>';
                foreach ($raw as $row) {
                    echo '<tr><td>' . (int) $row['line'] . '</td><td>' . (!empty($row['ok']) ? 'sim' : 'não') . '</td><td>' . esc_html(implode('; ', $row['errors'] ?? [])) . '</td></tr>';
                }
                echo '</tbody></table>';
            }
        }
    }

    private static function snippets(): void
    {
        $gen = new SnippetGenerator();
        echo '<h2>Snippets</h2>';
        echo '<p>Incavel: colar o bloco de location <strong>antes</strong> do proxy_pass atual. WP único: use o fallback sem reverse proxy.</p>';
        echo '<h3>Nginx map</h3><textarea class="large-text code" rows="10" readonly>' . esc_textarea($gen->nginxMapInclude()) . '</textarea>';
        echo '<h3>Nginx vhost (reverse proxy)</h3><textarea class="large-text code" rows="16" readonly>' . esc_textarea($gen->nginxLocation(true)) . '</textarea>';
        echo '<h3>Nginx mesmo servidor (WP único)</h3><textarea class="large-text code" rows="16" readonly>' . esc_textarea($gen->nginxLocation(false)) . '</textarea>';
        echo '<h3>Apache</h3><textarea class="large-text code" rows="10" readonly>' . esc_textarea($gen->apache()) . '</textarea>';
    }

    private static function row(string $label, string $html): void
    {
        echo '<tr><th>' . esc_html($label) . '</th><td>' . $html . '</td></tr>';
    }

    public static function save(): void
    {
        check_admin_referer('ffd_save_host');
        if (!current_user_can('manage_options')) {
            wp_die('Sem permissão');
        }
        $id = (int) ($_POST['id'] ?? 0);
        $request = new \WP_REST_Request('POST', '/');
        foreach (['host', 'post_id', 'https', 'flag_robots_index', 'flag_sitemap', 'flag_llm_txt', 'static_enabled', 'openseo_project_id', 'gsc_property'] as $key) {
            $request->set_param($key, $_POST[$key] ?? ($key === 'https' ? 0 : ''));
        }
        $routes = [];
        $paths = $_POST['route_path'] ?? [];
        $posts = $_POST['route_post_id'] ?? [];
        if (is_array($paths)) {
            foreach ($paths as $i => $path) {
                if (trim((string) $path) === '') {
                    continue;
                }
                $routes[] = [
                    'path' => $path,
                    'post_id' => (int) ($posts[$i] ?? 0),
                    'in_sitemap' => 1,
                ];
            }
        }
        $request->set_param('routes', $routes);
        if ($id > 0) {
            $request['id'] = $id;
            \FireflyDistill\Rest\HostsController::update($request);
        } else {
            \FireflyDistill\Rest\HostsController::store($request);
        }
        wp_safe_redirect(admin_url('admin.php?page=firefly-distill-hosts&ffd_notice=' . rawurlencode('Host salvo.')));
        exit;
    }

    public static function delete(): void
    {
        check_admin_referer('ffd_delete_host');
        if (!current_user_can('manage_options')) {
            wp_die('Sem permissão');
        }
        $request = new \WP_REST_Request('DELETE', '/');
        $request['id'] = (int) ($_POST['id'] ?? 0);
        \FireflyDistill\Rest\HostsController::destroy($request);
        wp_safe_redirect(admin_url('admin.php?page=firefly-distill-hosts&ffd_notice=' . rawurlencode('Host removido.')));
        exit;
    }

    public static function import(): void
    {
        check_admin_referer('ffd_import_hosts');
        if (!current_user_can('manage_options')) {
            wp_die('Sem permissão');
        }
        $dry = !empty($_POST['dry_run']);
        $rows = (new HostImporter())->parse((string) ($_POST['csv'] ?? ''), $dry);
        set_transient('ffd_import_preview', $rows, 120);
        if (!$dry) {
            (new HostsMapWriter())->write();
        }
        wp_safe_redirect(admin_url('admin.php?page=firefly-distill-hosts&ffd_import=1'));
        exit;
    }
}
