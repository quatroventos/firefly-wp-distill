<?php

namespace FireflyDistill\Hosts;

final class HostImporter
{
    public function __construct(private readonly HostRepository $hosts = new HostRepository())
    {
    }

    /**
     * @return list<array{line:int,ok:bool,errors:string[],row:array<string,mixed>}>
     */
    public function parse(string $csv, bool $dryRun = true): array
    {
        $lines = preg_split("/\r\n|\n|\r/", trim($csv)) ?: [];
        $results = [];
        $seenHosts = [];
        $seenRoots = [];
        $lineNo = 0;
        foreach ($lines as $raw) {
            $lineNo++;
            $raw = trim($raw);
            if ($raw === '' || str_starts_with($raw, '#')) {
                continue;
            }
            if ($lineNo === 1 && preg_match('/^host\s*,\s*post_id/i', $raw)) {
                continue;
            }
            $cols = str_getcsv($raw);
            $row = [
                'host' => HostValidator::normalize((string) ($cols[0] ?? '')),
                'post_id' => (int) ($cols[1] ?? 0),
                'path' => isset($cols[2]) && $cols[2] !== '' ? HostValidator::normalizePath((string) $cols[2]) : '/',
                'https' => $this->boolish($cols[3] ?? '1'),
                'robots' => $this->boolish($cols[4] ?? '1'),
                'sitemap' => $this->boolish($cols[5] ?? '1'),
                'llm' => $this->boolish($cols[6] ?? '1'),
                'static' => $this->boolish($cols[7] ?? '0'),
            ];
            $errors = $this->validateRow($row, $seenHosts, $seenRoots);
            $ok = $errors === [];
            if ($ok) {
                $seenHosts[$row['host']] = true;
                if ($row['path'] === '/') {
                    $seenRoots[$row['post_id']] = true;
                }
            }
            if ($ok && !$dryRun) {
                $this->commitRow($row);
            }
            $results[] = [
                'line' => $lineNo,
                'ok' => $ok,
                'errors' => $errors,
                'row' => $row,
            ];
        }
        return $results;
    }

    /**
     * @param array<string,mixed> $row
     * @param array<string,true> $seenHosts
     * @param array<int,true> $seenRoots
     * @return string[]
     */
    private function validateRow(array $row, array $seenHosts, array $seenRoots): array
    {
        $errors = [];
        if (!HostValidator::isValid($row['host'])) {
            $errors[] = 'host inválido';
        }
        if (isset($seenHosts[$row['host']]) || $this->hosts->existsHost($row['host'])) {
            $errors[] = 'host duplicado';
        }
        $postId = (int) $row['post_id'];
        if ($postId <= 0) {
            $errors[] = 'post_id inválido';
        } else {
            $post = get_post($postId);
            if (!$post || $post->post_status !== 'publish') {
                $errors[] = 'post inexistente ou não publicado';
            }
        }
        if ($row['path'] === '/' && (isset($seenRoots[$postId]) || $this->hosts->existsRootPost($postId))) {
            $errors[] = 'post raiz já mapeado em outro host';
        }
        return $errors;
    }

    /**
     * @param array<string,mixed> $row
     */
    private function commitRow(array $row): void
    {
        if ($row['path'] !== '/') {
            $existing = $this->hosts->findByHost($row['host']);
            if ($existing) {
                $routes = array_map(static fn ($r) => $r->toArray(), $existing->routes);
                $routes[] = [
                    'path' => $row['path'],
                    'post_id' => $row['post_id'],
                    'in_sitemap' => 1,
                ];
                $this->hosts->replaceRoutes($existing->id, $routes);
                return;
            }
        }
        $id = $this->hosts->insert([
            'host' => $row['host'],
            'post_id' => $row['path'] === '/' ? $row['post_id'] : 0,
            'https' => $row['https'],
            'flag_robots_index' => $row['robots'],
            'flag_sitemap' => $row['sitemap'],
            'flag_llm_txt' => $row['llm'],
            'static_enabled' => $row['static'],
        ]);
        if ($row['path'] !== '/') {
            $this->hosts->replaceRoutes($id, [[
                'path' => $row['path'],
                'post_id' => $row['post_id'],
                'in_sitemap' => 1,
            ]]);
        }
    }

    private function boolish(mixed $value): int
    {
        $v = strtolower(trim((string) $value));
        return in_array($v, ['1', 'true', 'yes', 'sim', 'on'], true) ? 1 : 0;
    }
}
