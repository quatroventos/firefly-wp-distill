<?php

namespace FireflyDistill\Model;

final class Build
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
            'build_id' => (string) $row['build_id'],
            'path' => (string) $row['path'],
            'is_current' => (int) $row['is_current'],
            'page_count' => (int) $row['page_count'],
            'created_at' => (string) $row['created_at'],
        ];
    }
}
