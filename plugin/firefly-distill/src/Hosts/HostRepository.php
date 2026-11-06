<?php

namespace FireflyDistill\Hosts;

use FireflyDistill\Model\Host;
use FireflyDistill\Model\HostRoute;

final class HostRepository
{
    private function table(): string
    {
        global $wpdb;
        return $wpdb->prefix . 'ffd_hosts';
    }

    private function routesTable(): string
    {
        global $wpdb;
        return $wpdb->prefix . 'ffd_host_routes';
    }

    /**
     * @return Host[]
     */
    public function all(): array
    {
        global $wpdb;
        $rows = $wpdb->get_results("SELECT * FROM {$this->table()} ORDER BY is_primary DESC, host ASC", ARRAY_A) ?: [];
        $hosts = array_map(static fn (array $row) => Host::fromRow($row), $rows);
        foreach ($hosts as $host) {
            $host->routes = $this->routesFor($host->id);
        }
        return $hosts;
    }

    public function find(int $id): ?Host
    {
        global $wpdb;
        $row = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM {$this->table()} WHERE id = %d", $id),
            ARRAY_A
        );
        if (!is_array($row)) {
            return null;
        }
        $host = Host::fromRow($row);
        $host->routes = $this->routesFor($host->id);
        return $host;
    }

    public function findByHost(string $host): ?Host
    {
        global $wpdb;
        $row = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM {$this->table()} WHERE host = %s", $host),
            ARRAY_A
        );
        if (!is_array($row)) {
            return null;
        }
        $model = Host::fromRow($row);
        $model->routes = $this->routesFor($model->id);
        return $model;
    }

    public function primary(): ?Host
    {
        global $wpdb;
        $row = $wpdb->get_row("SELECT * FROM {$this->table()} WHERE is_primary = 1 LIMIT 1", ARRAY_A);
        if (!is_array($row)) {
            return null;
        }
        $host = Host::fromRow($row);
        $host->routes = $this->routesFor($host->id);
        return $host;
    }

    public function findByRootPost(int $postId): ?Host
    {
        global $wpdb;
        $row = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM {$this->table()} WHERE post_id = %d LIMIT 1", $postId),
            ARRAY_A
        );
        return is_array($row) ? Host::fromRow($row) : null;
    }

    /**
     * @return Host[]
     */
    public function findByPost(int $postId): array
    {
        global $wpdb;
        $hosts = [];
        $root = $wpdb->get_results(
            $wpdb->prepare("SELECT * FROM {$this->table()} WHERE post_id = %d", $postId),
            ARRAY_A
        ) ?: [];
        foreach ($root as $row) {
            $h = Host::fromRow($row);
            $h->routes = $this->routesFor($h->id);
            $hosts[$h->id] = $h;
        }
        $joined = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT h.* FROM {$this->table()} h INNER JOIN {$this->routesTable()} r ON r.host_id = h.id WHERE r.post_id = %d",
                $postId
            ),
            ARRAY_A
        ) ?: [];
        foreach ($joined as $row) {
            $h = Host::fromRow($row);
            $h->routes = $this->routesFor($h->id);
            $hosts[$h->id] = $h;
        }
        return array_values($hosts);
    }

    /**
     * @return int[]
     */
    public function mappedPostIds(): array
    {
        global $wpdb;
        $root = $wpdb->get_col("SELECT post_id FROM {$this->table()} WHERE post_id > 0 AND is_primary = 0") ?: [];
        $routes = $wpdb->get_col("SELECT post_id FROM {$this->routesTable()} WHERE post_id > 0") ?: [];
        $ids = array_map('intval', array_unique(array_merge($root, $routes)));
        return array_values(array_filter($ids));
    }

    /**
     * @param array<string,mixed> $data
     */
    public function insert(array $data): int
    {
        global $wpdb;
        $now = current_time('mysql', true);
        $wpdb->insert($this->table(), [
            'host' => $data['host'],
            'post_id' => (int) ($data['post_id'] ?? 0),
            'https' => (int) ($data['https'] ?? 1),
            'flag_robots_index' => (int) ($data['flag_robots_index'] ?? 1),
            'flag_sitemap' => (int) ($data['flag_sitemap'] ?? 1),
            'flag_llm_txt' => (int) ($data['flag_llm_txt'] ?? 1),
            'static_enabled' => (int) ($data['static_enabled'] ?? 0),
            'openseo_project_id' => $data['openseo_project_id'] ?? null,
            'gsc_property' => $data['gsc_property'] ?? null,
            'current_build_id' => $data['current_build_id'] ?? null,
            'is_primary' => (int) ($data['is_primary'] ?? 0),
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        return (int) $wpdb->insert_id;
    }

    /**
     * @param array<string,mixed> $data
     */
    public function update(int $id, array $data): void
    {
        global $wpdb;
        $fields = [
            'updated_at' => current_time('mysql', true),
        ];
        foreach ([
            'host', 'post_id', 'https', 'flag_robots_index', 'flag_sitemap', 'flag_llm_txt',
            'static_enabled', 'openseo_project_id', 'gsc_property', 'current_build_id', 'is_primary',
        ] as $key) {
            if (array_key_exists($key, $data)) {
                $fields[$key] = $data[$key];
            }
        }
        $wpdb->update($this->table(), $fields, ['id' => $id]);
    }

    public function delete(int $id): void
    {
        global $wpdb;
        $wpdb->delete($this->routesTable(), ['host_id' => $id]);
        $wpdb->delete($this->table(), ['id' => $id]);
    }

    /**
     * @return HostRoute[]
     */
    public function routesFor(int $hostId): array
    {
        global $wpdb;
        $rows = $wpdb->get_results(
            $wpdb->prepare("SELECT * FROM {$this->routesTable()} WHERE host_id = %d ORDER BY path ASC", $hostId),
            ARRAY_A
        ) ?: [];
        return array_map(static fn (array $row) => HostRoute::fromRow($row), $rows);
    }

    /**
     * @param array<int,array<string,mixed>> $routes
     */
    public function replaceRoutes(int $hostId, array $routes): void
    {
        global $wpdb;
        $wpdb->delete($this->routesTable(), ['host_id' => $hostId]);
        foreach ($routes as $route) {
            $path = HostValidator::normalizePath((string) ($route['path'] ?? '/'));
            if ($path === '/') {
                continue;
            }
            $wpdb->insert($this->routesTable(), [
                'host_id' => $hostId,
                'path' => $path,
                'post_id' => (int) ($route['post_id'] ?? 0),
                'in_sitemap' => (int) ($route['in_sitemap'] ?? 1),
            ]);
        }
    }

    public function existsHost(string $host, ?int $exceptId = null): bool
    {
        global $wpdb;
        if ($exceptId) {
            $id = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM {$this->table()} WHERE host = %s AND id != %d",
                $host,
                $exceptId
            ));
        } else {
            $id = $wpdb->get_var($wpdb->prepare("SELECT id FROM {$this->table()} WHERE host = %s", $host));
        }
        return $id !== null;
    }

    public function existsRootPost(int $postId, ?int $exceptId = null): bool
    {
        if ($postId <= 0) {
            return false;
        }
        global $wpdb;
        if ($exceptId) {
            $id = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM {$this->table()} WHERE post_id = %d AND id != %d",
                $postId,
                $exceptId
            ));
        } else {
            $id = $wpdb->get_var($wpdb->prepare("SELECT id FROM {$this->table()} WHERE post_id = %d", $postId));
        }
        return $id !== null;
    }
}
