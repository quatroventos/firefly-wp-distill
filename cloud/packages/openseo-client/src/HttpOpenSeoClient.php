<?php

namespace Firefly\OpenSeo;

final class HttpOpenSeoClient implements OpenSeoClient
{
    public function __construct(
        private readonly string $baseUrl,
        private readonly string $token
    ) {
    }

    public function projectSlice(?string $projectId, string $host, string $path): array
    {
        if ($projectId === null || $projectId === '' || $this->baseUrl === '' || $this->token === '') {
            return (new FakeOpenSeoClient())->projectSlice($projectId, $host, $path);
        }
        $url = rtrim($this->baseUrl, '/') . '/projects/' . rawurlencode($projectId) . '/pages';
        $url .= '?host=' . rawurlencode($host) . '&path=' . rawurlencode($path);
        $payload = $this->get($url);
        return is_array($payload) ? $payload : (new FakeOpenSeoClient())->projectSlice($projectId, $host, $path);
    }

    public function opportunities(?string $projectId, string $host): array
    {
        if ($projectId === null || $projectId === '' || $this->baseUrl === '' || $this->token === '') {
            return [];
        }
        $url = rtrim($this->baseUrl, '/') . '/projects/' . rawurlencode($projectId) . '/opportunities?host=' . rawurlencode($host);
        $payload = $this->get($url);
        return isset($payload['items']) && is_array($payload['items']) ? $payload['items'] : [];
    }

    /**
     * @return array<string,mixed>|null
     */
    private function get(string $url): ?array
    {
        $ctx = stream_context_create([
            'http' => [
                'method' => 'GET',
                'header' => "Authorization: Bearer {$this->token}\r\nAccept: application/json\r\n",
                'timeout' => 15,
                'ignore_errors' => true,
            ],
        ]);
        $raw = @file_get_contents($url, false, $ctx);
        if (!is_string($raw) || $raw === '') {
            return null;
        }
        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : null;
    }
}
