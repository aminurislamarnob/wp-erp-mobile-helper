<?php
namespace WeLabs\WpErpAppHelper;

if ( ! class_exists( 'WP_List_Table' ) ) {
    require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

/**
 * RequiredApprovalListTable class
 */
class RequiredApprovalListTable extends \WP_List_Table {

    /**
     * Constructor
     */
    public function __construct() {
        parent::__construct( [
            'singular' => __( 'leave approval', 'wp-erp-app-helper' ),
            'plural'   => __( 'leave approvals', 'wp-erp-app-helper' ),
            'ajax'     => false
        ] );
    }

    /**
     * Message to show if no items found
     */
    public function no_items() {
        _e( 'No leave requests found that require your approval.', 'wp-erp-app-helper' );
    }

    /**
     * Get columns
     *
     * @return array
     */
    public function get_columns() {
        return [
            'employee'   => __( 'Employee', 'wp-erp-app-helper' ),
            'leave_type' => __( 'Leave Type', 'wp-erp-app-helper' ),
            'duration'   => __( 'Duration', 'wp-erp-app-helper' ),
            'reason'     => __( 'Reason', 'wp-erp-app-helper' ),
            'status'     => __( 'Current Status', 'wp-erp-app-helper' ),
            'actions'    => __( 'Actions', 'wp-erp-app-helper' ),
        ];
    }

    /**
     * Prepare items
     */
    public function prepare_items() {
        global $wpdb;

        $columns               = $this->get_columns();
        $hidden                = [];
        $sortable              = [];
        $this->_column_headers = [ $columns, $hidden, $sortable ];

        $current_user_id = get_current_user_id();

        // Fetch leave requests where current user is the required approver
        $results = $wpdb->get_results( $wpdb->prepare(
            "SELECT r.*, l.name as leave_name, u.display_name as employee_name
            FROM {$wpdb->prefix}erp_hr_leave_requests r
            LEFT JOIN {$wpdb->prefix}erp_hr_leaves l ON r.leave_id = l.id
            LEFT JOIN {$wpdb->users} u ON r.user_id = u.ID
            WHERE r.required_approver = %d AND r.approval_status = %s",
            $current_user_id, 'Pending'
        ) );

        $this->items = $results;
    }

    /**
     * Column default
     */
    public function column_default( $item, $column_name ) {
        switch ( $column_name ) {
            case 'employee':
                return '<strong>' . esc_html( $item->employee_name ) . '</strong>';
            case 'leave_type':
                return esc_html( $item->leave_name );
            case 'duration':
                return erp_format_date( $item->start_date ) . ' ' . __( 'to', 'wp-erp-app-helper' ) . ' ' . erp_format_date( $item->end_date );
            case 'reason':
                return '<em>' . esc_html( $item->reason ) . '</em>';
            case 'status':
                return '<span class="erp-app-helper-approval-badge status-pending">' . __( 'Pending Your Approval', 'wp-erp-app-helper' ) . '</span>';
            case 'actions':
                return sprintf(
                    '<a href="%s" class="button button-primary erp-app-helper-approve">%s</a> <a href="%s" class="button button-secondary erp-app-helper-reject">%s</a>',
                    wp_nonce_url( admin_url( 'admin.php?page=erp-hr&section=leave-approvals&action=approve&id=' . $item->id ), 'erp-app-helper-action' ),
                    __( 'Approve', 'wp-erp-app-helper' ),
                    wp_nonce_url( admin_url( 'admin.php?page=erp-hr&section=leave-approvals&action=reject&id=' . $item->id ), 'erp-app-helper-action' ),
                    __( 'Reject', 'wp-erp-app-helper' )
                );
            default:
                return print_r( $item, true );
        }
    }
}
