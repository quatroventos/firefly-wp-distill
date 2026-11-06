<?php

namespace FireflyDistill\Model;

final class Page
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
            'source_url' => (string) $row['source_url'],
            'output_relpath' => (string) $row['output_relpath'],
            'content_hash' => (string) $row['content_hash'],
        ];
    }
}
