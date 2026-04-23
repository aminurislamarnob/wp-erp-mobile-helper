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
        parent::__construct(
            [
				'singular' => __( 'leave approval', 'wp-erp-app-helper' ),
				'plural'   => __( 'leave approvals', 'wp-erp-app-helper' ),
				'ajax'     => false,
			]
        );
    }

    /**
     * Message to show if no items found
     */
    public function no_items() {
        esc_html_e( 'No leave requests found that require your approval.', 'wp-erp-app-helper' );
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
     * Get views
     */
    protected function get_views() {
        global $wpdb;
        $current_user_id = get_current_user_id();

        $counts = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT approval_status, COUNT(*) as count 
            FROM {$wpdb->prefix}erp_hr_leave_requests 
            WHERE required_approver = %d 
            GROUP BY approval_status",
                $current_user_id
            ), OBJECT_K
        );

        $pending_count  = isset( $counts['Pending'] ) ? $counts['Pending']->count : 0;
        $approved_count = isset( $counts['Approved'] ) ? $counts['Approved']->count : 0;
        $rejected_count = isset( $counts['Rejected'] ) ? $counts['Rejected']->count : 0;

        $current = isset( $_GET['status'] ) ? sanitize_text_field( wp_unslash( $_GET['status'] ) ) : 'Pending'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

        $views = [
            'Pending'  => sprintf( '<a href="%s" class="%s">%s <span class="count">(%d)</span></a>', admin_url( 'admin.php?page=erp-hr&section=leave-approvals&status=Pending' ), ( 'Pending' === $current ? 'current' : '' ), __( 'Pending', 'wp-erp-app-helper' ), $pending_count ),
            'Approved' => sprintf( '<a href="%s" class="%s">%s <span class="count">(%d)</span></a>', admin_url( 'admin.php?page=erp-hr&section=leave-approvals&status=Approved' ), ( 'Approved' === $current ? 'current' : '' ), __( 'Approved', 'wp-erp-app-helper' ), $approved_count ),
            'Rejected' => sprintf( '<a href="%s" class="%s">%s <span class="count">(%d)</span></a>', admin_url( 'admin.php?page=erp-hr&section=leave-approvals&status=Rejected' ), ( 'Rejected' === $current ? 'current' : '' ), __( 'Rejected', 'wp-erp-app-helper' ), $rejected_count ),
        ];

        return $views;
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
        $status = isset( $_GET['status'] ) ? sanitize_text_field( wp_unslash( $_GET['status'] ) ) : 'Pending'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

        // Fetch leave requests where current user is the required approver
        $results = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT r.*, l.name as leave_name, u.display_name as employee_name
            FROM {$wpdb->prefix}erp_hr_leave_requests r
            LEFT JOIN {$wpdb->prefix}erp_hr_leaves l ON r.leave_id = l.id
            LEFT JOIN {$wpdb->users} u ON r.user_id = u.ID
            WHERE r.required_approver = %d AND r.approval_status = %s",
                $current_user_id, $status
            )
        );

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
                $status = $item->approval_status ? $item->approval_status : 'Pending';
                $class = 'status-' . strtolower( $status );
                return sprintf( '<span class="erp-app-helper-approval-badge %s">%s</span>', $class, $status );
            case 'actions':
                if ( 'Pending' !== $item->approval_status ) {
                    return '—';
                }
                return sprintf(
                    '<button type="button" class="button button-primary erp-app-helper-action-btn" data-action="approve" data-id="%d" data-name="%s">%s</button> ' .
                    '<button type="button" class="button erp-app-helper-action-btn" data-action="reject" data-id="%d" data-name="%s">%s</button>',
                    $item->id, esc_attr( $item->employee_name ), __( 'Approve', 'wp-erp-app-helper' ),
                    $item->id, esc_attr( $item->employee_name ), __( 'Reject', 'wp-erp-app-helper' )
                );
            default:
                return print_r( $item, true );
        }
    }
}
