<?php

namespace FireflyDistill\Admin;

use FireflyDistill\Hosts\HostRepository;

if (!class_exists('\WP_List_Table')) {
    require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

final class HostsListTable extends \WP_List_Table
{
    public function __construct()
    {
        parent::__construct([
            'singular' => 'host',
            'plural' => 'hosts',
            'ajax' => false,
        ]);
    }

    /**
     * @return array<string,string>
     */
    public function get_columns(): array
    {
        return [
            'host' => 'Host público',
            'https' => 'HTTPS',
            'root' => 'Post raiz',
            'urls' => 'Permalink interno × URL pública',
            'flags' => 'Flags',
            'static' => 'Estático',
            'build' => 'Build atual',
            'openseo' => 'OpenSEO',
        ];
    }

    public function prepare_items(): void
    {
        $this->_column_headers = [$this->get_columns(), [], []];
        $this->items = (new HostRepository())->all();
    }

    public function column_host($item): string
    {
        $edit = admin_url('admin.php?page=firefly-distill-hosts&host_id=' . (int) $item->id);
        $primary = (int) $item->is_primary === 1 ? ' <span class="ffd-badge">primário</span>' : '';
        return '<a href="' . esc_url($edit) . '"><code>' . esc_html($item->host) . '</code></a>' . $primary;
    }

    public function column_https($item): string
    {
        return (int) $item->https === 1 ? 'sim' : 'não';
    }

    public function column_root($item): string
    {
        if ((int) $item->post_id <= 0) {
            return '—';
        }
        $title = get_the_title((int) $item->post_id);
        $edit = get_edit_post_link((int) $item->post_id);
        $label = esc_html($title) . ' <code>#' . (int) $item->post_id . '</code>';
        return $edit ? '<a href="' . esc_url($edit) . '">' . $label . '</a>' : $label;
    }

    public function column_urls($item): string
    {
        $data = $item->toArray();
        return '<span class="ffd-muted">' . esc_html($data['internal_permalink']) . '</span><br><strong>' . esc_html($data['public_url']) . '</strong>';
    }

    public function column_flags($item): string
    {
        $bits = [];
        $bits[] = (int) $item->flag_robots_index ? 'index' : 'noindex';
        $bits[] = (int) $item->flag_sitemap ? 'sitemap' : 'sem sitemap';
        $bits[] = (int) $item->flag_llm_txt ? 'llm.txt' : 'sem llm.txt';
        return esc_html(implode(' · ', $bits));
    }

    public function column_static($item): string
    {
        return (int) $item->static_enabled === 1 ? 'ligado' : 'desligado';
    }

    public function column_build($item): string
    {
        return $item->current_build_id ? '<code>' . esc_html($item->current_build_id) . '</code>' : '—';
    }

    public function column_openseo($item): string
    {
        return $item->openseo_project_id ? esc_html($item->openseo_project_id) : '<span class="ffd-muted">herda do site</span>';
    }

    public function column_default($item, $column_name): string
    {
        return '';
    }
}
