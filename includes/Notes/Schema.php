<?php

namespace WeLabs\WpErpAppHelper\Notes;

/**
 * Schema — creates the four tables backing the user-notes feature.
 */
class Schema {

    const DB_VERSION        = '1.0.0';
    const DB_VERSION_OPTION = 'wp_erp_app_helper_notes_db_version';

    public static function notes_table() {
        global $wpdb;
        return $wpdb->prefix . 'erp_app_helper_notes';
    }

    public static function labels_table() {
        global $wpdb;
        return $wpdb->prefix . 'erp_app_helper_note_labels';
    }

    public static function relationships_table() {
        global $wpdb;
        return $wpdb->prefix . 'erp_app_helper_note_label_relationships';
    }

    public static function attachments_table() {
        global $wpdb;
        return $wpdb->prefix . 'erp_app_helper_note_attachments';
    }

    /**
     * Create / update tables. Safe to call repeatedly.
     */
    public function install() {
        global $wpdb;

        $collate = $wpdb->has_cap( 'collation' ) ? $wpdb->get_charset_collate() : '';

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $notes = self::notes_table();
        $labels = self::labels_table();
        $rels  = self::relationships_table();
        $atts  = self::attachments_table();

        dbDelta(
            "CREATE TABLE IF NOT EXISTS `$notes` (
                `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                `user_id` bigint(20) unsigned NOT NULL,
                `title` varchar(200) NOT NULL,
                `content` longtext NULL,
                `is_pinned` tinyint(1) NOT NULL DEFAULT 0,
                `is_archived` tinyint(1) NOT NULL DEFAULT 0,
                `created_at` datetime NOT NULL,
                `updated_at` datetime NOT NULL,
                PRIMARY KEY (`id`),
                KEY `user_id` (`user_id`),
                KEY `is_pinned` (`is_pinned`),
                KEY `is_archived` (`is_archived`),
                KEY `created_at` (`created_at`)
            ) $collate;"
        );

        dbDelta(
            "CREATE TABLE IF NOT EXISTS `$labels` (
                `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                `user_id` bigint(20) unsigned NOT NULL,
                `name` varchar(50) NOT NULL,
                `color` char(7) NOT NULL,
                `description` varchar(200) NULL,
                `created_at` datetime NOT NULL,
                `updated_at` datetime NOT NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `user_name` (`user_id`,`name`)
            ) $collate;"
        );

        dbDelta(
            "CREATE TABLE IF NOT EXISTS `$rels` (
                `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                `note_id` bigint(20) unsigned NOT NULL,
                `label_id` bigint(20) unsigned NOT NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `note_label` (`note_id`,`label_id`),
                KEY `label_id` (`label_id`)
            ) $collate;"
        );

        dbDelta(
            "CREATE TABLE IF NOT EXISTS `$atts` (
                `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                `note_id` bigint(20) unsigned NOT NULL,
                `attachment_id` bigint(20) unsigned NOT NULL,
                `created_at` datetime NOT NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `note_attachment` (`note_id`,`attachment_id`),
                KEY `note_id` (`note_id`)
            ) $collate;"
        );

        update_option( self::DB_VERSION_OPTION, self::DB_VERSION );
    }
}
