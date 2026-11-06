<?php

namespace FireflyDistill\Model;

final class Suggestion
{
    /**
     * @param array<string,mixed> $row
     * @return array<string,mixed>
     */
    public static function fromRow(array $row): array
    {
        return [
            'id' => (int) $row['id'],
            'cloud_suggestion_id' => (string) $row['cloud_suggestion_id'],
            'host' => (string) $row['host'],
            'url' => (string) $row['url'],
            'category' => (string) $row['category'],
            'severity' => (string) $row['severity'],
            'title' => (string) $row['title'],
            'body' => (string) $row['body'],
            'status' => (string) $row['status'],
            'source' => (string) $row['source'],
        ];
    }
}
