<?php

namespace FireflyDistill\Rest;

use FireflyDistill\Crawl\BypassToken;
use FireflyDistill\Hosts\HostImporter;
use FireflyDistill\Hosts\HostRepository;
use FireflyDistill\Hosts\HostsMapWriter;
use FireflyDistill\Hosts\HostValidator;
use FireflyDistill\Queue\CloudClient;
use FireflyDistill\Queue\Enqueuer;
use FireflyDistill\Serving\Publisher;
use WP_REST_Request;
use WP_REST_Response;

final class HostsController
{
    public static function index(): WP_REST_Response
    {
        $hosts = array_map(static fn ($h) => $h->toArray(), (new HostRepository())->all());
        return new WP_REST_Response(['hosts' => $hosts]);
    }

    public static function show(WP_REST_Request $request): WP_REST_Response
    {
        $host = (new HostRepository())->find((int) $request['id']);
        if (!$host) {
            return new WP_REST_Response(['message' => 'host não encontrado'], 404);
        }
        return new WP_REST_Response($host->toArray());
    }

    public static function store(WP_REST_Request $request): WP_REST_Response
    {
        $parsed = self::parseBody($request);
        if (isset($parsed['error'])) {
            return new WP_REST_Response(['message' => $parsed['error']], 422);
        }
        $repo = new HostRepository();
        if ($repo->existsHost($parsed['host'])) {
            return new WP_REST_Response(['message' => 'host duplicado'], 422);
        }
        if ($repo->existsRootPost((int) $parsed['post_id'])) {
            return new WP_REST_Response(['message' => 'post raiz já mapeado'], 422);
        }
        $id = $repo->insert($parsed);
        $repo->replaceRoutes($id, $parsed['routes']);
        self::afterChange();
        return new WP_REST_Response($repo->find($id)?->toArray(), 201);
    }

    public static function update(WP_REST_Request $request): WP_REST_Response
    {
        $repo = new HostRepository();
        $existing = $repo->find((int) $request['id']);
        if (!$existing) {
            return new WP_REST_Response(['message' => 'host não encontrado'], 404);
        }
        $parsed = self::parseBody($request);
        if (isset($parsed['error'])) {
            return new WP_REST_Response(['message' => $parsed['error']], 422);
        }
        if ($repo->existsHost($parsed['host'], $existing->id)) {
            return new WP_REST_Response(['message' => 'host duplicado'], 422);
        }
        if ($repo->existsRootPost((int) $parsed['post_id'], $existing->id)) {
            return new WP_REST_Response(['message' => 'post raiz já mapeado'], 422);
        }
        $flagsOnly = self::flagsOnlyChanged($existing->toArray(), $parsed);
        $repo->update($existing->id, $parsed);
        $repo->replaceRoutes($existing->id, $parsed['routes']);
        self::afterChange();
        if ($flagsOnly) {
            (new Enqueuer())->enqueueHost($parsed['host'], '/', 'https://' . $parsed['host'] . '/', (int) $parsed['post_id'], 'host_meta');
        }
        return new WP_REST_Response($repo->find($existing->id)?->toArray());
    }

    public static function destroy(WP_REST_Request $request): WP_REST_Response
    {
        $repo = new HostRepository();
        $host = $repo->find((int) $request['id']);
        if (!$host) {
            return new WP_REST_Response(['message' => 'host não encontrado'], 404);
        }
        if ((int) $host->is_primary === 1) {
            return new WP_REST_Response(['message' => 'não é permitido apagar o host primário'], 422);
        }
        $repo->delete($host->id);
        self::afterChange();
        return new WP_REST_Response(['ok' => true]);
    }

    public static function import(WP_REST_Request $request): WP_REST_Response
    {
        $csv = (string) $request->get_param('csv');
        $dry = (bool) $request->get_param('dry_run');
        $rows = (new HostImporter())->parse($csv, $dry);
        if (!$dry) {
            self::afterChange();
        }
        return new WP_REST_Response(['rows' => $rows, 'dry_run' => $dry]);
    }

    public static function searchPosts(WP_REST_Request $request): WP_REST_Response
    {
        $q = sanitize_text_field((string) $request->get_param('q'));
        $query = new \WP_Query([
            's' => $q,
            'post_type' => array_values(get_post_types(['public' => true])),
            'post_status' => 'publish',
            'posts_per_page' => 20,
            'no_found_rows' => true,
        ]);
        $items = [];
        foreach ($query->posts as $post) {
            $items[] = [
                'id' => (int) $post->ID,
                'title' => get_the_title($post),
                'type' => $post->post_type,
                'permalink' => get_permalink($post),
            ];
        }
        return new WP_REST_Response($items);
    }

    public static function rebuild(WP_REST_Request $request): WP_REST_Response
    {
        $host = (new HostRepository())->find((int) $request['id']);
        if (!$host) {
            return new WP_REST_Response(['message' => 'host não encontrado'], 404);
        }
        $scheme = $host->https ? 'https' : 'http';
        (new Enqueuer())->enqueueHost($host->host, '/', $scheme . '://' . $host->host . '/', $host->post_id, 'manual');
        Enqueuer::flushHost($host->host);
        return new WP_REST_Response(['ok' => true]);
    }

    public static function rollback(WP_REST_Request $request): WP_REST_Response
    {
        $host = (new HostRepository())->find((int) $request['id']);
        if (!$host) {
            return new WP_REST_Response(['message' => 'host não encontrado'], 404);
        }
        $prev = (new Publisher())->rollback($host->host);
        if ($prev === null) {
            return new WP_REST_Response(['message' => 'sem build anterior'], 422);
        }
        return new WP_REST_Response(['ok' => true, 'build_id' => $prev]);
    }

    public static function testHost(WP_REST_Request $request): WP_REST_Response
    {
        $host = (new HostRepository())->find((int) $request['id']);
        if (!$host) {
            return new WP_REST_Response(['message' => 'host não encontrado'], 404);
        }
        $scheme = $host->https ? 'https' : 'http';
        $url = $scheme . '://' . $host->host . '/';
        $public = self::head($url);
        $bypassUrl = BypassToken::originUrl($url, $host->host, '/');
        $origin = self::head($bypassUrl);
        return new WP_REST_Response([
            'public' => $public,
            'bypass' => $origin,
        ]);
    }

    /**
     * @return array{status:int,xffd:string}
     */
    private static function head(string $url): array
    {
        $res = wp_remote_head($url, ['timeout' => 10, 'redirection' => 0, 'sslverify' => false]);
        if (is_wp_error($res)) {
            return ['status' => 0, 'xffd' => '', 'error' => $res->get_error_message()];
        }
        $headers = wp_remote_retrieve_headers($res);
        $xffd = '';
        if (is_object($headers) && method_exists($headers, 'offsetGet')) {
            $xffd = (string) ($headers['x-ffd'] ?? '');
        } elseif (is_array($headers)) {
            $xffd = (string) ($headers['x-ffd'] ?? $headers['X-FFD'] ?? '');
        }
        return [
            'status' => (int) wp_remote_retrieve_response_code($res),
            'xffd' => $xffd,
        ];
    }

    /**
     * @return array<string,mixed>
     */
    private static function parseBody(WP_REST_Request $request): array
    {
        $host = HostValidator::normalize((string) $request->get_param('host'));
        if (!HostValidator::isValid($host)) {
            return ['error' => 'host inválido'];
        }
        $postId = (int) $request->get_param('post_id');
        $routesIn = $request->get_param('routes');
        $routes = [];
        if (is_array($routesIn)) {
            foreach ($routesIn as $route) {
                if (!is_array($route)) {
                    continue;
                }
                $routes[] = [
                    'path' => HostValidator::normalizePath((string) ($route['path'] ?? '/')),
                    'post_id' => (int) ($route['post_id'] ?? 0),
                    'in_sitemap' => (int) ($route['in_sitemap'] ?? 1),
                ];
            }
        }
        return [
            'host' => $host,
            'post_id' => $postId,
            'https' => (int) ($request->get_param('https') ?? 1),
            'flag_robots_index' => (int) ($request->get_param('flag_robots_index') ?? 1),
            'flag_sitemap' => (int) ($request->get_param('flag_sitemap') ?? 1),
            'flag_llm_txt' => (int) ($request->get_param('flag_llm_txt') ?? 1),
            'static_enabled' => (int) ($request->get_param('static_enabled') ?? 0),
            'openseo_project_id' => sanitize_text_field((string) ($request->get_param('openseo_project_id') ?? '')),
            'gsc_property' => sanitize_text_field((string) ($request->get_param('gsc_property') ?? '')),
            'routes' => $routes,
        ];
    }

    /**
     * @param array<string,mixed> $old
     * @param array<string,mixed> $new
     */
    private static function flagsOnlyChanged(array $old, array $new): bool
    {
        $identity = ['host', 'post_id'];
        foreach ($identity as $key) {
            if ((string) $old[$key] !== (string) $new[$key]) {
                return false;
            }
        }
        foreach (['flag_robots_index', 'flag_sitemap', 'flag_llm_txt', 'static_enabled'] as $flag) {
            if ((int) $old[$flag] !== (int) $new[$flag]) {
                return true;
            }
        }
        return false;
    }

    private static function afterChange(): void
    {
        (new HostsMapWriter())->write();
        $repo = new HostRepository();
        $payload = array_map(static fn ($h) => $h->toArray(), $repo->all());
        (new CloudClient())->syncHosts($payload);
    }
}
