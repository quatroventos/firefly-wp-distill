<?php

namespace FireflyDistill\Rest;

use FireflyDistill\Hosts\HostRepository;
use FireflyDistill\Hosts\HostValidator;
use FireflyDistill\Security\PathGuard;
use FireflyDistill\Serving\Publisher;
use FireflyDistill\Serving\StoragePaths;
use WP_REST_Request;
use WP_REST_Response;

final class ReceiveBuildController
{
    public static function issueToken(WP_REST_Request $request): WP_REST_Response
    {
        $token = bin2hex(random_bytes(24));
        $host = HostValidator::normalize((string) $request->get_param('host'));
        $buildId = sanitize_file_name((string) $request->get_param('build_id'));
        set_transient('ffd_job_token_' . md5($token), $token, 2 * HOUR_IN_SECONDS);
        set_transient('ffd_job_meta_' . md5($token), ['host' => $host, 'build_id' => $buildId], 2 * HOUR_IN_SECONDS);
        return new WP_REST_Response(['job_token' => $token]);
    }

    public static function store(WP_REST_Request $request): WP_REST_Response
    {
        $host = HostValidator::normalize((string) $request->get_param('host'));
        $buildId = sanitize_file_name((string) $request->get_param('build_id'));
        $manifest = $request->get_param('manifest');
        $files = $request->get_param('files');
        $activate = (bool) $request->get_param('activate');

        if (!HostValidator::isValid($host) || $buildId === '') {
            return new WP_REST_Response(['message' => 'host ou build_id inválido'], 422);
        }
        if (!(new HostRepository())->findByHost($host)) {
            return new WP_REST_Response(['message' => 'host não mapeado'], 422);
        }
        if (!is_array($manifest) || !is_array($files)) {
            return new WP_REST_Response(['message' => 'manifest/files obrigatórios'], 422);
        }

        StoragePaths::ensureTree();
        $dir = StoragePaths::buildDir($host, $buildId);
        wp_mkdir_p($dir);

        foreach ($files as $file) {
            if (!is_array($file)) {
                return self::fail($dir, 'arquivo inválido');
            }
            $rel = (string) ($file['path'] ?? '');
            if (!PathGuard::isSafeRelative($rel)) {
                return self::fail($dir, 'path rejeitado: ' . $rel);
            }
            $target = PathGuard::resolveUnder($dir, $rel);
            if ($target === null) {
                return self::fail($dir, 'path fora da árvore: ' . $rel);
            }
            $raw = base64_decode((string) ($file['content_base64'] ?? ''), true);
            if ($raw === false) {
                return self::fail($dir, 'base64 inválido: ' . $rel);
            }
            $expected = (string) ($file['sha256'] ?? '');
            if ($expected !== '' && !hash_equals($expected, hash('sha256', $raw))) {
                return self::fail($dir, 'checksum inválido: ' . $rel);
            }
            wp_mkdir_p(dirname($target));
            file_put_contents($target, $raw);
        }

        file_put_contents($dir . '/manifest.json', wp_json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        global $wpdb;
        $table = $wpdb->prefix . 'ffd_builds';
        $wpdb->insert($table, [
            'host' => $host,
            'build_id' => $buildId,
            'path' => $dir,
            'is_current' => 0,
            'page_count' => is_array($manifest['pages'] ?? null) ? count($manifest['pages']) : 0,
            'lighthouse_summary' => isset($manifest['lighthouse_summary']) ? wp_json_encode($manifest['lighthouse_summary']) : null,
            'created_at' => current_time('mysql', true),
        ]);

        if (isset($manifest['pages']) && is_array($manifest['pages'])) {
            $pages = $wpdb->prefix . 'ffd_pages';
            foreach ($manifest['pages'] as $page) {
                if (!is_array($page)) {
                    continue;
                }
                $wpdb->insert($pages, [
                    'host' => $host,
                    'build_id' => $buildId,
                    'path' => (string) ($page['path'] ?? '/'),
                    'source_url' => (string) ($page['source_url'] ?? ''),
                    'output_relpath' => (string) ($page['output_relpath'] ?? ''),
                    'content_hash' => (string) ($page['origin_hash'] ?? str_repeat('0', 64)),
                    'post_modified_gmt' => $page['post_modified_gmt'] ?? null,
                    'lh_scores' => isset($page['lh_scores']) ? wp_json_encode($page['lh_scores']) : null,
                ]);
            }
        }

        if ($activate) {
            (new Publisher())->activate($host, $buildId);
        }

        return new WP_REST_Response(['ok' => true, 'build_id' => $buildId]);
    }

    private static function fail(string $dir, string $message): WP_REST_Response
    {
        return new WP_REST_Response(['message' => $message], 422);
    }
}
