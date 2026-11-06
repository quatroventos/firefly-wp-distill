<?php

namespace FireflyDistill\Model;

final class PageAudit
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
            'phase' => (string) $row['phase'],
            'performance' => $row['performance'] !== null ? (int) $row['performance'] : null,
            'seo' => $row['seo'] !== null ? (int) $row['seo'] : null,
            'accessibility' => $row['accessibility'] !== null ? (int) $row['accessibility'] : null,
            'best_practices' => $row['best_practices'] !== null ? (int) $row['best_practices'] : null,
            'audited_at' => (string) $row['audited_at'],
        ];
    }
}
