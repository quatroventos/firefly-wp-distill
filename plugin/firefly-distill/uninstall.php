<?php

defined('WP_UNINSTALL_PLUGIN') || exit;

global $wpdb;

$confirm = get_option('ffd_confirm_purge_builds', '');
$root = wp_upload_dir();
$dir = trailingslashit($root['basedir']) . 'firefly-distill';

$tables = [
    $wpdb->prefix . 'ffd_suggestions',
    $wpdb->prefix . 'ffd_page_audits',
    $wpdb->prefix . 'ffd_pages',
    $wpdb->prefix . 'ffd_builds',
    $wpdb->prefix . 'ffd_queue',
    $wpdb->prefix . 'ffd_host_routes',
    $wpdb->prefix . 'ffd_hosts',
];

foreach ($tables as $table) {
    $wpdb->query("DROP TABLE IF EXISTS {$table}"); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
}

delete_option('ffd_settings');
delete_option('ffd_hmac_secret');
delete_option('ffd_schema_version');
delete_option('ffd_hosts_map_reload_pending');
delete_option('ffd_confirm_purge_builds');
delete_transient('ffd_nonce_replay');

if ($confirm === 'purge-builds' && is_dir($dir)) {
    // Só apaga builds se a option de confirmação estiver setada antes do uninstall.
    $iterator = new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS);
    $files = new RecursiveIteratorIterator($iterator, RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($files as $file) {
        $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
    }
    rmdir($dir);
}
