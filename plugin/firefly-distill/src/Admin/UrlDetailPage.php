<?php

namespace FireflyDistill\Admin;

use FireflyDistill\Crawl\BypassToken;
use FireflyDistill\Hosts\HostValidator;

final class UrlDetailPage
{
    public static function render(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die('Sem permissão');
        }
        $url = esc_url_raw((string) ($_GET['url'] ?? ''));
        echo '<div class="wrap ffd-wrap"><h1>URL</h1>';
        if ($url === '') {
            echo '<p>Informe <code>?url=</code>.</p></div>';
            return;
        }
        echo '<p><a href="' . esc_url($url) . '" target="_blank" rel="noopener">' . esc_html($url) . '</a></p>';
        $host = HostValidator::normalize((string) wp_parse_url($url, PHP_URL_HOST));
        $path = HostValidator::normalizePath((string) (wp_parse_url($url, PHP_URL_PATH) ?: '/'));
        $origin = BypassToken::originUrl($url, $host, $path);
        echo '<p>Origem (bypass): <a href="' . esc_url($origin) . '" target="_blank" rel="noopener">abrir WP</a></p>';

        global $wpdb;
        $audits = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}ffd_page_audits WHERE url = %s ORDER BY audited_at DESC",
            $url
        ), ARRAY_A) ?: [];
        echo '<h2>Audits</h2><table class="widefat"><thead><tr><th>Fase</th><th>Perf</th><th>SEO</th><th>A11y</th><th>BP</th><th>Quando</th></tr></thead><tbody>';
        foreach ($audits as $a) {
            echo '<tr><td>' . esc_html((string) $a['phase']) . '</td><td>' . esc_html((string) $a['performance']) . '</td><td>' . esc_html((string) $a['seo']) . '</td><td>' . esc_html((string) $a['accessibility']) . '</td><td>' . esc_html((string) $a['best_practices']) . '</td><td>' . esc_html((string) $a['audited_at']) . '</td></tr>';
        }
        echo '</tbody></table>';

        $sugs = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}ffd_suggestions WHERE url = %s ORDER BY updated_at DESC",
            $url
        ), ARRAY_A) ?: [];
        echo '<h2>Sugestões</h2>';
        foreach ($sugs as $s) {
            echo '<div class="ffd-card"><strong>' . esc_html((string) $s['title']) . '</strong> <span class="ffd-muted">' . esc_html((string) $s['severity'] . ' · ' . $s['status']) . '</span><p>' . wp_kses_post((string) $s['body']) . '</p></div>';
        }
        echo '</div>';
    }
}
