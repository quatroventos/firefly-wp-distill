<?php

namespace Firefly\OpenSeo;

interface OpenSeoClient
{
    /**
     * @return array<string,mixed>
     */
    public function projectSlice(?string $projectId, string $host, string $path): array;

    /**
     * @return list<array<string,mixed>>
     */
    public function opportunities(?string $projectId, string $host): array;
}
