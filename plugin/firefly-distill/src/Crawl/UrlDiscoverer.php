<?php

namespace FireflyDistill\Crawl;

use FireflyDistill\Hosts\HostRepository;
use FireflyDistill\Hosts\HostValidator;

final class UrlDiscoverer
{
    public const MAX_PER_HOST = 50;

    public function __construct(private readonly HostRepository $hosts = new HostRepository())
    {
    }

    /**
     * @return list<array{host:string,path:string,post_id:int,post_modified_gmt:string,url:string}>
     */
    public function forHost(?string $host = null): array
    {
        $out = [];
        $list = $host ? array_filter([$this->hosts->findByHost(HostValidator::normalize($host))]) : $this->hosts->all();
        foreach ($list as $item) {
            if ($item === null) {
                continue;
            }
            $scheme = $item->https ? 'https' : 'http';
            $out[] = $this->row($item->host, '/', $item->post_id, $scheme);
            foreach ($item->routes as $route) {
                $out[] = $this->row($item->host, $route->path, $route->post_id, $scheme);
            }
            if ((int) $item->is_primary === 1) {
                $out = array_merge($out, $this->discoverPrimaryExtras($item->host, $scheme, $item->post_id));
            }
            $out = array_slice($this->unique($out), 0, self::MAX_PER_HOST);
        }
        return $out;
    }

    /**
     * @return list<array{host:string,path:string,post_id:int,post_modified_gmt:string,url:string}>
     */
    private function discoverPrimaryExtras(string $host, string $scheme, int $frontId): array
    {
        $mapped = (new HostRepository())->mappedPostIds();
        $q = new \WP_Query([
            'post_type' => $this->publicTypes(),
            'post_status' => 'publish',
            'posts_per_page' => self::MAX_PER_HOST,
            'post__not_in' => $mapped,
            'no_found_rows' => true,
            'fields' => 'ids',
        ]);
        $rows = [];
        foreach ($q->posts as $id) {
            $id = (int) $id;
            if ($id === $frontId) {
                continue;
            }
            $permalink = (string) get_permalink($id);
            $path = HostValidator::normalizePath((string) (wp_parse_url($permalink, PHP_URL_PATH) ?: '/'));
            $rows[] = $this->row($host, $path, $id, $scheme);
        }
        return $rows;
    }

    /**
     * @return string[]
     */
    private function publicTypes(): array
    {
        $types = get_post_types(['public' => true], 'names');
        unset($types['attachment']);
        return array_values($types);
    }

    /**
     * @return array{host:string,path:string,post_id:int,post_modified_gmt:string,url:string}
     */
    private function row(string $host, string $path, int $postId, string $scheme): array
    {
        $path = HostValidator::normalizePath($path);
        $modified = '';
        if ($postId > 0) {
            $post = get_post($postId);
            $modified = $post ? (string) $post->post_modified_gmt : '';
        }
        $url = $scheme . '://' . $host . ($path === '/' ? '/' : rtrim($path, '/'));
        return [
            'host' => $host,
            'path' => $path,
            'post_id' => $postId,
            'post_modified_gmt' => $modified,
            'url' => $url,
        ];
    }

    /**
     * @param list<array{host:string,path:string,post_id:int,post_modified_gmt:string,url:string}> $rows
     * @return list<array{host:string,path:string,post_id:int,post_modified_gmt:string,url:string}>
     */
    private function unique(array $rows): array
    {
        $seen = [];
        $out = [];
        foreach ($rows as $row) {
            $key = $row['host'] . '|' . $row['path'];
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $out[] = $row;
        }
        return $out;
    }
}
