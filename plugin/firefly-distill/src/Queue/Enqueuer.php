<?php

namespace FireflyDistill\Queue;

use FireflyDistill\Hosts\HostResolver;

final class Enqueuer
{
    public function __construct(
        private readonly HostResolver $resolver = new HostResolver(),
        private readonly CloudClient $cloud = new CloudClient()
    ) {
    }

    public function enqueuePost(int $postId, string $reason): void
    {
        if ($postId <= 0) {
            return;
        }
        foreach ($this->resolver->forPost($postId) as $item) {
            $this->push($item['host'], $item['path'], $item['url'], $postId, $reason);
        }
    }

    public function enqueueHost(string $host, string $path, string $url, int $postId, string $reason): void
    {
        $this->push($host, $path, $url, $postId, $reason);
    }

    public function enqueueAllHosts(string $reason): void
    {
        $hosts = (new \FireflyDistill\Hosts\HostRepository())->all();
        foreach ($hosts as $host) {
            $scheme = $host->https ? 'https' : 'http';
            $this->push($host->host, '/', $scheme . '://' . $host->host . '/', $host->post_id, $reason);
        }
    }

    private function push(string $host, string $path, string $url, int $postId, string $reason): int
    {
        global $wpdb;
        $table = $wpdb->prefix . 'ffd_queue';
        $wpdb->insert($table, [
            'host' => $host,
            'path' => $path,
            'url' => $url,
            'post_id' => $postId,
            'reason' => $reason,
            'status' => 'pending',
            'enqueued_at' => current_time('mysql', true),
        ]);
        Debounce::schedule($host);
        return (int) $wpdb->insert_id;
    }

    public static function flushHost(string $host): void
    {
        (new self())->flush($host);
    }

    public function flush(string $host): array
    {
        global $wpdb;
        $table = $wpdb->prefix . 'ffd_queue';
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$table} WHERE host = %s AND status = %s ORDER BY id ASC",
            $host,
            'pending'
        ), ARRAY_A) ?: [];
        if ($rows === []) {
            return ['ok' => true, 'sent' => 0];
        }
        $items = array_map(static function (array $row): array {
            return [
                'host' => $row['host'],
                'path' => $row['path'],
                'post_id' => (int) $row['post_id'],
                'reason' => $row['reason'],
                'url' => $row['url'],
            ];
        }, $rows);
        $result = $this->cloud->enqueue($items);
        $status = $result['ok'] ? 'sent' : 'error';
        $jobId = (string) ($result['body']['job_id'] ?? '');
        foreach ($rows as $row) {
            $wpdb->update($table, [
                'status' => $status,
                'cloud_job_id' => $jobId !== '' ? $jobId : null,
                'processed_at' => current_time('mysql', true),
            ], ['id' => (int) $row['id']]);
        }
        return $result + ['sent' => count($rows)];
    }
}
