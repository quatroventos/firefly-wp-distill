<?php

namespace FireflyDistill\Hosts;

use FireflyDistill\Serving\StoragePaths;

final class HostsMapWriter
{
    public function __construct(private readonly HostRepository $hosts = new HostRepository())
    {
    }

    public function write(): string
    {
        StoragePaths::ensureTree();
        $lines = [];
        foreach ($this->hosts->all() as $host) {
            if ((int) $host->static_enabled !== 1) {
                continue;
            }
            if (!HostValidator::isValid($host->host)) {
                continue;
            }
            $lines[] = sprintf('%s %s;', $host->host, $host->host);
        }
        $content = $lines === [] ? "# firefly-distill hosts.map.conf — vazio\n" : implode("\n", $lines) . "\n";
        $path = StoragePaths::mapFile();
        file_put_contents($path, $content);
        update_option('ffd_hosts_map_reload_pending', 1, false);
        return $path;
    }

    public function pendingReload(): bool
    {
        return (int) get_option('ffd_hosts_map_reload_pending', 0) === 1;
    }

    public function clearReloadFlag(): void
    {
        delete_option('ffd_hosts_map_reload_pending');
    }
}
