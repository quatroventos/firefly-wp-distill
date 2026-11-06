<?php

namespace FireflyDistill\Admin;

use FireflyDistill\Hosts\HostRepository;

final class DashboardPage
{
    public static function render(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die('Sem permissão');
        }
        global $wpdb;
        $filter = sanitize_text_field((string) ($_GET['host'] ?? ''));
        $hosts = (new HostRepository())->all();
        $sql = "SELECT * FROM {$wpdb->prefix}ffd_page_audits";
        $args = [];
        if ($filter !== '') {
            $sql .= ' WHERE host = %s';
            $args[] = $filter;
        }
        $sql .= ' ORDER BY host ASC, path ASC, phase ASC';
        $rows = $args ? $wpdb->get_results($wpdb->prepare($sql, ...$args), ARRAY_A) : $wpdb->get_results($sql, ARRAY_A);
        $rows = $rows ?: [];

        $open = $wpdb->get_results("SELECT url, COUNT(*) c FROM {$wpdb->prefix}ffd_suggestions WHERE status = 'open' GROUP BY url", OBJECT_K) ?: [];

        echo '<div class="wrap ffd-wrap"><h1>Dashboard</h1>';
        echo '<form method="get"><input type="hidden" name="page" value="firefly-distill-dashboard" />';
        echo '<select name="host"><option value="">todos os hosts</option>';
        foreach ($hosts as $host) {
            echo '<option value="' . esc_attr($host->host) . '" ' . selected($filter, $host->host, false) . '>' . esc_html($host->host) . '</option>';
        }
        echo '</select> ';
        submit_button('Filtrar', 'secondary', '', false);
        echo '</form>';

        $grouped = [];
        foreach ($rows as $row) {
            $grouped[$row['host']][] = $row;
        }
        foreach ($grouped as $host => $audits) {
            $avg = self::averages($audits);
            echo '<h2>' . esc_html($host) . '</h2>';
            echo '<p>Médias (build): Perf ' . $avg['performance'] . ' · SEO ' . $avg['seo'] . ' · A11y ' . $avg['accessibility'] . ' · BP ' . $avg['best_practices'] . '</p>';
            echo '<table class="widefat"><thead><tr><th>URL pública</th><th>Fase</th><th>Perf</th><th>SEO</th><th>A11y</th><th>BP</th><th>Sugestões</th></tr></thead><tbody>';
            foreach ($audits as $a) {
                $count = isset($open[$a['url']]) ? (int) $open[$a['url']]->c : 0;
                $detail = admin_url('admin.php?page=firefly-distill-url&url=' . rawurlencode((string) $a['url']));
                echo '<tr><td><a href="' . esc_url($detail) . '">' . esc_html((string) $a['url']) . '</a></td><td>' . esc_html((string) $a['phase']) . '</td>';
                echo '<td>' . esc_html((string) $a['performance']) . '</td><td>' . esc_html((string) $a['seo']) . '</td><td>' . esc_html((string) $a['accessibility']) . '</td><td>' . esc_html((string) $a['best_practices']) . '</td>';
                echo '<td>' . $count . '</td></tr>';
            }
            echo '</tbody></table>';
        }
        if ($grouped === []) {
            echo '<p>Nenhum audit ainda.</p>';
        }
        echo '</div>';
    }

    /**
     * @param list<array<string,mixed>> $audits
     * @return array<string,string>
     */
    private static function averages(array $audits): array
    {
        $keys = ['performance', 'seo', 'accessibility', 'best_practices'];
        $out = [];
        foreach ($keys as $key) {
            $vals = [];
            foreach ($audits as $a) {
                if (($a['phase'] ?? '') === 'build' && $a[$key] !== null && $a[$key] !== '') {
                    $vals[] = (int) $a[$key];
                }
            }
            $out[$key] = $vals === [] ? '—' : (string) (int) round(array_sum($vals) / count($vals));
        }
        return $out;
    }
}
