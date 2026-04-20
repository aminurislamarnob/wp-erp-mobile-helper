<?php

namespace WeLabs\WpErpAppHelper;

class Installer {
    
    /**
     * Run the installer
     */
    public function run() {
        $this->create_tables();
        $this->add_db_columns();
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
    /**
     * Add necessary columns to existing WP-ERP tables
     */
    private function add_db_columns() {
        global $wpdb;

        $table_name = "{$wpdb->prefix}erp_hr_leave_requests";
        
        $columns = [
            'required_approver' => "bigint(20) UNSIGNED DEFAULT NULL",
            'approval_status'   => "varchar(20) DEFAULT NULL", // Pending, Approved, Rejected
            'approval_message'  => "text DEFAULT NULL",
        ];

        foreach ( $columns as $column => $definition ) {
            $row = $wpdb->get_results( "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE table_name = '$table_name' AND column_name = '$column'" );

            if ( empty( $row ) ) {
                $wpdb->query( "ALTER TABLE `$table_name` ADD `$column` $definition" );
            }
        }
    }
}
