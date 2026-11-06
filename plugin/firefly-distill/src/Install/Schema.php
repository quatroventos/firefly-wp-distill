<?php

namespace FireflyDistill\Install;

final class Schema
{
    public function maybeUpgrade(): void
    {
        $installed = (string) get_option('ffd_schema_version', '');
        if ($installed === FFD_SCHEMA_VERSION) {
            return;
        }
        self::install();
    }

    public static function install(): void
    {
        global $wpdb;
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $charset = $wpdb->get_charset_collate();
        $p = $wpdb->prefix;

        dbDelta("CREATE TABLE {$p}ffd_hosts (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            host VARCHAR(253) NOT NULL,
            post_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
            https TINYINT(1) NOT NULL DEFAULT 1,
            flag_robots_index TINYINT(1) NOT NULL DEFAULT 1,
            flag_sitemap TINYINT(1) NOT NULL DEFAULT 1,
            flag_llm_txt TINYINT(1) NOT NULL DEFAULT 1,
            static_enabled TINYINT(1) NOT NULL DEFAULT 0,
            openseo_project_id VARCHAR(64) NULL,
            gsc_property VARCHAR(255) NULL,
            current_build_id VARCHAR(64) NULL,
            is_primary TINYINT(1) NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY host (host),
            KEY post_id (post_id)
        ) {$charset};");

        dbDelta("CREATE TABLE {$p}ffd_host_routes (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            host_id BIGINT UNSIGNED NOT NULL,
            path VARCHAR(255) NOT NULL,
            post_id BIGINT UNSIGNED NOT NULL,
            in_sitemap TINYINT(1) NOT NULL DEFAULT 1,
            PRIMARY KEY  (id),
            UNIQUE KEY host_path (host_id, path),
            KEY post_id (post_id)
        ) {$charset};");

        dbDelta("CREATE TABLE {$p}ffd_queue (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            host VARCHAR(253) NOT NULL,
            path VARCHAR(255) NOT NULL,
            url TEXT NOT NULL,
            post_id BIGINT UNSIGNED NULL,
            reason VARCHAR(64) NOT NULL,
            status VARCHAR(32) NOT NULL DEFAULT 'pending',
            cloud_job_id VARCHAR(64) NULL,
            enqueued_at DATETIME NOT NULL,
            processed_at DATETIME NULL,
            PRIMARY KEY  (id),
            KEY host_status (host, status),
            KEY status (status)
        ) {$charset};");

        dbDelta("CREATE TABLE {$p}ffd_builds (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            host VARCHAR(253) NOT NULL,
            build_id VARCHAR(64) NOT NULL,
            path TEXT NOT NULL,
            is_current TINYINT(1) NOT NULL DEFAULT 0,
            page_count INT NOT NULL DEFAULT 0,
            lighthouse_summary LONGTEXT NULL,
            created_at DATETIME NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY host_build (host, build_id),
            KEY host_current (host, is_current)
        ) {$charset};");

        dbDelta("CREATE TABLE {$p}ffd_pages (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            host VARCHAR(253) NOT NULL,
            build_id VARCHAR(64) NOT NULL,
            path VARCHAR(255) NOT NULL,
            source_url TEXT NOT NULL,
            output_relpath TEXT NOT NULL,
            content_hash CHAR(64) NOT NULL,
            post_modified_gmt DATETIME NULL,
            lh_scores LONGTEXT NULL,
            PRIMARY KEY  (id),
            KEY host_build (host, build_id),
            KEY host_path (host, path)
        ) {$charset};");

        dbDelta("CREATE TABLE {$p}ffd_page_audits (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            host VARCHAR(253) NOT NULL,
            path VARCHAR(255) NOT NULL,
            url TEXT NOT NULL,
            phase VARCHAR(16) NOT NULL,
            performance SMALLINT NULL,
            seo SMALLINT NULL,
            accessibility SMALLINT NULL,
            best_practices SMALLINT NULL,
            psi_json LONGTEXT NULL,
            openseo_slice LONGTEXT NULL,
            audited_at DATETIME NOT NULL,
            PRIMARY KEY  (id),
            KEY host_path (host, path)
        ) {$charset};");

        dbDelta("CREATE TABLE {$p}ffd_suggestions (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            cloud_suggestion_id VARCHAR(64) NOT NULL,
            host VARCHAR(253) NOT NULL,
            url TEXT NOT NULL,
            category VARCHAR(32) NOT NULL,
            severity VARCHAR(16) NOT NULL,
            title VARCHAR(255) NOT NULL,
            body TEXT NOT NULL,
            status VARCHAR(16) NOT NULL DEFAULT 'open',
            source VARCHAR(32) NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY cloud_id (cloud_suggestion_id),
            KEY host_status (host, status)
        ) {$charset};");

        update_option('ffd_schema_version', FFD_SCHEMA_VERSION, false);
    }
}
