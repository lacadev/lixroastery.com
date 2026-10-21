<?php

namespace App\Features\LacaSeo\Modules\Links;

defined('ABSPATH') || exit;

/**
 * Creates database tables for Link Counter and Link Genius.
 */
class Installer
{
    public static function createTables(): void
    {
        global $wpdb;

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $charsetCollate = $wpdb->get_charset_collate();

        // 1. Internal Links
        $linksTable = $wpdb->prefix . 'laca_seo_internal_links';
        $sqlLinks = "CREATE TABLE {$linksTable} (
            id bigint(20) unsigned NOT NULL auto_increment,
            url varchar(255) NOT NULL,
            post_id bigint(20) unsigned NOT NULL,
            target_post_id bigint(20) unsigned NOT NULL,
            type varchar(8) NOT NULL,
            anchor_text varchar(500) DEFAULT NULL,
            anchor_type varchar(10) DEFAULT 'HPLNK',
            is_nofollow tinyint(1) DEFAULT 0,
            target_blank tinyint(1) DEFAULT 0,
            url_hash char(32) DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY link_direction (post_id, type),
            KEY target_post_id (target_post_id),
            KEY idx_url_hash (url_hash),
            KEY idx_post_id (post_id),
            KEY idx_type (type),
            KEY idx_anchor_type (anchor_type),
            KEY idx_target_blank (target_blank),
            KEY idx_is_nofollow (is_nofollow),
            KEY idx_created_at (created_at)
        ) {$charsetCollate};";

        // 2. Internal Meta
        $metaTable = $wpdb->prefix . 'laca_seo_internal_meta';
        $sqlMeta = "CREATE TABLE {$metaTable} (
            object_id bigint(20) unsigned NOT NULL,
            internal_link_count int(10) unsigned NULL default 0,
            external_link_count int(10) unsigned NULL default 0,
            incoming_link_count int(10) unsigned NULL default 0,
            PRIMARY KEY  (object_id)
        ) {$charsetCollate};";

        // 3. Link Genius Audit
        $auditTable = $wpdb->prefix . 'laca_seo_link_genius_audit';
        $sqlAudit = "CREATE TABLE {$auditTable} (
            id bigint(20) unsigned NOT NULL auto_increment,
            link_id bigint(20) unsigned NOT NULL,
            url_hash char(32) NOT NULL,
            http_status_code smallint(3) DEFAULT NULL,
            status_category varchar(10) DEFAULT NULL,
            robots_blocked tinyint(1) DEFAULT 0,
            is_marked_safe tinyint(1) DEFAULT 0,
            last_checked_at datetime DEFAULT NULL,
            last_error_message text DEFAULT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY idx_link_id (link_id),
            KEY idx_url_hash (url_hash),
            KEY idx_status_code (http_status_code),
            KEY idx_status_category (status_category),
            KEY idx_last_checked (last_checked_at),
            KEY idx_robots_blocked (robots_blocked),
            KEY idx_marked_safe (is_marked_safe)
        ) {$charsetCollate};";

        // 4. Link Genius History
        $historyTable = $wpdb->prefix . 'laca_seo_link_genius_history';
        $sqlHistory = "CREATE TABLE {$historyTable} (
            id bigint(20) unsigned NOT NULL auto_increment,
            batch_id char(32) NOT NULL,
            source_type varchar(20) NOT NULL DEFAULT 'bulk_update',
            keyword_map_id bigint(20) unsigned DEFAULT NULL,
            user_id bigint(20) unsigned NOT NULL,
            operation_type varchar(20) NOT NULL,
            filters longtext NOT NULL,
            changes_summary longtext NOT NULL,
            affected_links_count int(10) unsigned NOT NULL DEFAULT 0,
            affected_posts_count int(10) unsigned NOT NULL DEFAULT 0,
            status varchar(20) NOT NULL DEFAULT 'pending',
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            completed_at datetime DEFAULT NULL,
            error_message text DEFAULT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY idx_batch_id (batch_id),
            KEY idx_user_id (user_id),
            KEY idx_status (status),
            KEY idx_created_at (created_at)
        ) {$charsetCollate};";

        // 5. Link Genius Snapshots
        $snapshotsTable = $wpdb->prefix . 'laca_seo_link_genius_snapshots';
        $sqlSnapshots = "CREATE TABLE {$snapshotsTable} (
            id bigint(20) unsigned NOT NULL auto_increment,
            batch_id char(32) NOT NULL,
            post_id bigint(20) unsigned NOT NULL,
            original_content longtext NOT NULL,
            link_changes longtext NOT NULL,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY idx_batch_id (batch_id),
            KEY idx_post_id (post_id),
            KEY idx_batch_post (batch_id, post_id)
        ) {$charsetCollate};";

        dbDelta($sqlLinks);
        dbDelta($sqlMeta);
        dbDelta($sqlAudit);
        dbDelta($sqlHistory);
        dbDelta($sqlSnapshots);
    }
}
