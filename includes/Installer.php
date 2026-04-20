<?php

namespace WeLabs\WpErpAppHelper;

class Installer {
    
    /**
     * Run the installer
     */
    public function run() {
        $this->create_tables();
    }

    /**
     * Create database tables
     */
    private function create_tables() {
        global $wpdb;

        $collate = '';

        if ( $wpdb->has_cap( 'collation' ) ) {
            $collate = $wpdb->get_charset_collate();
        }

        $table_schema = [
            "CREATE TABLE IF NOT EXISTS `{$wpdb->prefix}erp_standup_tracker` (
                `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                `employee_id` bigint(20) unsigned NOT NULL,
                `standup_date` date NOT NULL,
                `status` varchar(20) NOT NULL,
                `created_by` bigint(20) unsigned NOT NULL,
                `created_at` datetime NOT NULL,
                `updated_at` datetime NOT NULL,
                PRIMARY KEY (`id`),
                KEY `employee_id` (`employee_id`),
                KEY `standup_date` (`standup_date`)
            ) $collate;"
        ];

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        foreach ( $table_schema as $table ) {
            dbDelta( $table );
        }
    }
}
