<?php

namespace Firefly\OpenSeo;

final class FakeOpenSeoClient implements OpenSeoClient
{
    public function projectSlice(?string $projectId, string $host, string $path): array
    {
        return [
            'project_id' => $projectId,
            'host' => $host,
            'path' => $path,
            'clicks' => 0,
            'impressions' => 0,
            'ctr' => 0,
            'position' => null,
            'indexed' => null,
            'queries' => [],
        ];
    }

    public function opportunities(?string $projectId, string $host): array
    {
        return [];
    }
}
