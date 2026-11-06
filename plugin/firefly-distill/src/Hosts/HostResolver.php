<?php

namespace FireflyDistill\Hosts;

final class HostResolver
{
    public function __construct(private readonly HostRepository $hosts = new HostRepository())
    {
    }

    /**
     * @return list<array{host:string,path:string,post_id:int,url:string}>
     */
    public function forPost(int $postId): array
    {
        $out = [];
        $mapped = $this->hosts->findByPost($postId);
        foreach ($mapped as $host) {
            if ((int) $host->post_id === $postId) {
                $out[] = $this->item($host->host, '/', $postId, (bool) $host->https);
            }
            foreach ($host->routes as $route) {
                if ((int) $route->post_id === $postId) {
                    $out[] = $this->item($host->host, $route->path, $postId, (bool) $host->https);
                }
            }
        }
        if ($out !== []) {
            return $out;
        }
        $primary = $this->hosts->primary();
        if ($primary === null) {
            return [];
        }
        $permalink = function_exists('get_permalink') ? (string) get_permalink($postId) : '';
        $path = '/';
        if ($permalink !== '') {
            $parsed = wp_parse_url($permalink, PHP_URL_PATH);
            $path = HostValidator::normalizePath(is_string($parsed) ? $parsed : '/');
        }
        $out[] = $this->item($primary->host, $path, $postId, (bool) $primary->https);
        return $out;
    }

    /**
     * @return array{host:string,path:string,post_id:int,url:string}
     */
    private function item(string $host, string $path, int $postId, bool $https): array
    {
        $path = HostValidator::normalizePath($path);
        $url = ($https ? 'https://' : 'http://') . $host . ($path === '/' ? '/' : rtrim($path, '/'));
        return [
            'host' => $host,
            'path' => $path,
            'post_id' => $postId,
            'url' => $url,
        ];
    }
}
