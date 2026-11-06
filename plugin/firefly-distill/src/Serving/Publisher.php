<?php

namespace FireflyDistill\Serving;

use FireflyDistill\Hosts\HostRepository;

final class Publisher
{
    public function __construct(private readonly HostRepository $hosts = new HostRepository())
    {
    }

    public function activate(string $host, string $buildId): void
    {
        $host = StoragePaths::assertHost($host);
        $buildDir = StoragePaths::buildDir($host, $buildId);
        if (!is_dir($buildDir)) {
            throw new \RuntimeException('build inexistente');
        }
        StoragePaths::ensureTree();
        $currentDir = StoragePaths::root() . '/current';
        wp_mkdir_p($currentDir);

        $link = StoragePaths::currentLink($host);
        $tmp = $link . '.tmp';
        $target = '../builds/' . $host . '/' . $buildId;

        if (file_exists($tmp) || is_link($tmp)) {
            unlink($tmp);
        }
        if (!symlink($target, $tmp)) {
            throw new \RuntimeException('falha ao criar symlink temporário');
        }
        if (!rename($tmp, $link)) {
            unlink($tmp);
            throw new \RuntimeException('falha ao ativar symlink');
        }

        global $wpdb;
        $table = $wpdb->prefix . 'ffd_builds';
        $wpdb->update($table, ['is_current' => 0], ['host' => $host]);
        $wpdb->update($table, ['is_current' => 1], ['host' => $host, 'build_id' => $buildId]);
        $this->hosts->update((int) $this->hosts->findByHost($host)?->id, [
            'current_build_id' => $buildId,
        ]);
        $this->purgeOld($host);
    }

    public function rollback(string $host): ?string
    {
        global $wpdb;
        $host = StoragePaths::assertHost($host);
        $table = $wpdb->prefix . 'ffd_builds';
        $current = $wpdb->get_var($wpdb->prepare(
            "SELECT build_id FROM {$table} WHERE host = %s AND is_current = 1",
            $host
        ));
        $previous = $wpdb->get_var($wpdb->prepare(
            "SELECT build_id FROM {$table} WHERE host = %s AND build_id != %s ORDER BY created_at DESC LIMIT 1",
            $host,
            (string) $current
        ));
        if (!is_string($previous) || $previous === '') {
            return null;
        }
        $this->activate($host, $previous);
        return $previous;
    }

    private function purgeOld(string $host): void
    {
        $settings = get_option('ffd_settings', []);
        $keep = (int) ($settings['retention_builds'] ?? 5);
        if ($keep < 1) {
            $keep = 5;
        }
        global $wpdb;
        $table = $wpdb->prefix . 'ffd_builds';
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT id, build_id, is_current FROM {$table} WHERE host = %s ORDER BY created_at DESC",
            $host
        ), ARRAY_A) ?: [];
        $i = 0;
        foreach ($rows as $row) {
            $i++;
            if ((int) $row['is_current'] === 1 || $i <= $keep) {
                continue;
            }
            $dir = StoragePaths::buildDir($host, (string) $row['build_id']);
            $this->rmTree($dir);
            $wpdb->delete($table, ['id' => (int) $row['id']]);
        }
    }

    private function rmTree(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        $it = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($it as $file) {
            $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
        }
        rmdir($dir);
    }
}
