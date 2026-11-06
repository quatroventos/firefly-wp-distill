<?php

namespace FireflyDistill\Model;

final class QueueItem
{
    /**
     * @param array<string,mixed> $row
     * @return array<string,mixed>
     */
    public static function fromRow(array $row): array
    {
        return [
            'id' => (int) $row['id'],
            'host' => (string) $row['host'],
            'path' => (string) $row['path'],
            'url' => (string) $row['url'],
            'post_id' => (int) $row['post_id'],
            'reason' => (string) $row['reason'],
            'status' => (string) $row['status'],
            'cloud_job_id' => $row['cloud_job_id'],
            'enqueued_at' => (string) $row['enqueued_at'],
            'processed_at' => $row['processed_at'],
        ];
    }
}
