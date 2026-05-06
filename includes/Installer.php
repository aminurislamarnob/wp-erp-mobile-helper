<?php

namespace WeLabs\WpErpAppHelper;

use WeLabs\WpErpAppHelper\Notes\Schema as NotesSchema;

class Installer {

    /**
     * Run the installer
     */
    public function run() {
        $this->create_tables();
        $this->add_db_columns();
        ( new NotesSchema() )->install();
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
            ) $collate;",
        ];

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        foreach ( $table_schema as $table ) {
            dbDelta( $table );
        }

        $this->create_payment_requests_table( $collate );
        $this->create_payment_request_attachments_table( $collate );
    }

    /**
     * Create erp_payment_requests table
     */
    private function create_payment_requests_table( $collate ) {
        global $wpdb;

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $sql = "CREATE TABLE IF NOT EXISTS `{$wpdb->prefix}erp_payment_requests` (
            `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            `employee_id` bigint(20) unsigned NOT NULL,
            `created_by` bigint(20) unsigned DEFAULT NULL,
            `title` varchar(255) NOT NULL,
            `amount` decimal(10,2) NOT NULL DEFAULT '0.00',
            `description` text NOT NULL,
            `status` varchar(20) NOT NULL DEFAULT 'pending',
            `hr_note` text DEFAULT NULL,
            `reviewed_by` bigint(20) unsigned DEFAULT NULL,
            `reviewed_at` datetime DEFAULT NULL,
            `created_at` datetime NOT NULL,
            `updated_at` datetime NOT NULL,
            PRIMARY KEY (`id`),
            KEY `employee_id` (`employee_id`),
            KEY `status` (`status`)
        ) $collate;";

        dbDelta( $sql );
    }

    /**
     * Create erp_payment_request_attachments table
     */
    private function create_payment_request_attachments_table( $collate ) {
        global $wpdb;

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $sql = "CREATE TABLE IF NOT EXISTS `{$wpdb->prefix}erp_payment_request_attachments` (
            `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            `request_id` bigint(20) unsigned NOT NULL,
            `attachment_id` bigint(20) unsigned NOT NULL,
            `file_type` varchar(10) NOT NULL,
            PRIMARY KEY (`id`),
            KEY `request_id` (`request_id`)
        ) $collate;";

        dbDelta( $sql );
    }
    /**
     * Add necessary columns to existing WP-ERP tables
     */
    private function add_db_columns() {
        global $wpdb;

        $table_name = "{$wpdb->prefix}erp_hr_leave_requests";

        $columns = [
            'required_approver' => 'bigint(20) UNSIGNED DEFAULT NULL',
            'approval_status'   => 'varchar(20) DEFAULT NULL', // Pending, Approved, Rejected
            'approval_message'  => 'text DEFAULT NULL',
        ];

        foreach ( $columns as $column => $definition ) {
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table/column names cannot use placeholders in INFORMATION_SCHEMA queries.
            $row = $wpdb->get_results( "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE table_name = '$table_name' AND column_name = '$column'" );

            if ( empty( $row ) ) {
                // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.SchemaChange -- DDL ALTER TABLE requires interpolation for identifiers.
                $wpdb->query( "ALTER TABLE `$table_name` ADD `$column` $definition" );
            }
        }

        $pr_table   = "{$wpdb->prefix}erp_payment_requests";
        $pr_columns = [
            'created_by'        => 'bigint(20) UNSIGNED DEFAULT NULL',
            'payment_type'      => 'varchar(50) DEFAULT NULL',
            'purchase_date'     => 'date DEFAULT NULL',
            'expect_payment_by' => 'date DEFAULT NULL',
        ];

        foreach ( $pr_columns as $column => $definition ) {
            $row = $wpdb->get_results( "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE table_name = '$pr_table' AND column_name = '$column'" ); // phpcs:ignore

            if ( empty( $row ) ) {
                $wpdb->query( "ALTER TABLE `$pr_table` ADD `$column` $definition" ); // phpcs:ignore
            }
        }
    }
}
