<?php

namespace App\Features\LacaSeo\Modules\Redirections;

defined('ABSPATH') || exit;

/**
 * Creates and updates tables for Redirections and 404 Monitor.
 * Uses dbDelta matching Rank Math table structure with laca_seo_ prefix.
 */
class Installer
{
    public const DB_VERSION = '1.0.0';
    public const DB_VERSION_OPTION = 'laca_seo_db_version';

    /**
     * Create or update database tables.
     */
    public static function createTables(bool $force = false): void
    {
        global $wpdb;

        $tableRedirections = $wpdb->prefix . 'laca_seo_redirections';
        $currentVer = get_option(self::DB_VERSION_OPTION, '');

        if (!$force && $currentVer === self::DB_VERSION) {
            $tableExists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $tableRedirections));
            if ($tableExists) {
                return;
            }
        }

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $charsetCollate = $wpdb->get_charset_collate();

        // 1. Redirections table
        $tableRedirections = $wpdb->prefix . 'laca_seo_redirections';
        $sqlRedirections = "CREATE TABLE {$tableRedirections} (
            id bigint(20) unsigned NOT NULL auto_increment,
            sources longtext CHARACTER SET {$wpdb->charset} COLLATE {$wpdb->charset}_bin NOT NULL,
            url_to text NOT NULL,
            header_code smallint(4) unsigned NOT NULL,
            hits bigint(20) unsigned NOT NULL default '0',
            status varchar(25) NOT NULL default 'active',
            created datetime NOT NULL default '0000-00-00 00:00:00',
            updated datetime NOT NULL default '0000-00-00 00:00:00',
            last_accessed datetime NOT NULL default '0000-00-00 00:00:00',
            PRIMARY KEY  (id),
            KEY status (status),
            KEY idx_laca_status_updated (status, updated)
        ) {$charsetCollate};";

        // 2. Redirections Cache table
        $tableCache = $wpdb->prefix . 'laca_seo_redirections_cache';
        $sqlCache = "CREATE TABLE {$tableCache} (
            id bigint(20) unsigned NOT NULL auto_increment,
            from_url text CHARACTER SET {$wpdb->charset} COLLATE {$wpdb->charset}_bin NOT NULL,
            redirection_id bigint(20) unsigned NOT NULL,
            object_id bigint(20) unsigned NOT NULL default '0',
            object_type varchar(10) NOT NULL default 'post',
            is_redirected tinyint(1) NOT NULL default '0',
            PRIMARY KEY  (id),
            KEY redirection_id (redirection_id)
        ) {$charsetCollate};";

        // 3. 404 Logs table
        $tableLogs = $wpdb->prefix . 'laca_seo_404_logs';
        $sqlLogs = "CREATE TABLE {$tableLogs} (
            id bigint(20) unsigned NOT NULL auto_increment,
            uri varchar(255) NOT NULL,
            accessed datetime NOT NULL default '0000-00-00 00:00:00',
            times_accessed bigint(20) unsigned NOT NULL default 1,
            referer varchar(255) NOT NULL default '',
            user_agent varchar(255) NOT NULL default '',
            PRIMARY KEY  (id),
            KEY uri (uri(191))
        ) {$charsetCollate};";

        dbDelta($sqlRedirections);
        dbDelta($sqlCache);
        dbDelta($sqlLogs);

        update_option(self::DB_VERSION_OPTION, self::DB_VERSION);
    }
}
