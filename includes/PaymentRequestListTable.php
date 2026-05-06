<?php
namespace WeLabs\WpErpAppHelper;

if ( ! class_exists( 'WP_List_Table' ) ) {
    require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

/**
 * PaymentRequestListTable — HR view of all payment requests
 */
class PaymentRequestListTable extends \WP_List_Table {

    /**
     * Get currently selected employee filter value.
     */
    private function get_selected_employee_id() {
        return isset( $_GET['employee_id'] ) ? absint( $_GET['employee_id'] ) : 0; // phpcs:ignore
    }

    /**
     * Get currently selected status filter value.
     */
    private function get_selected_status() {
        $status = isset( $_GET['status'] ) ? sanitize_text_field( $_GET['status'] ) : 'pending'; // phpcs:ignore

        if ( ! in_array( $status, [ 'all', 'pending', 'approved', 'rejected' ], true ) ) {
            $status = 'pending';
        }

        return $status;
    }

    public function __construct() {
        parent::__construct(
            [
				'singular' => __( 'payment request', 'wp-erp-app-helper' ),
				'plural'   => __( 'payment requests', 'wp-erp-app-helper' ),
				'ajax'     => false,
			]
        );
    }

    public function no_items() {
        esc_html_e( 'No payment requests found.', 'wp-erp-app-helper' );
    }

    public function get_columns() {
        return [
            'employee'    => __( 'Employee', 'wp-erp-app-helper' ),
            'title'       => __( 'Title', 'wp-erp-app-helper' ),
            'amount'      => __( 'Amount', 'wp-erp-app-helper' ),
            'description' => __( 'Description', 'wp-erp-app-helper' ),
            'attachments' => __( 'Attachments', 'wp-erp-app-helper' ),
            'submitted'   => __( 'Submitted', 'wp-erp-app-helper' ),
            'expected_payment_date' => __( 'Expected Date', 'wp-erp-app-helper' ),
            'status'      => __( 'Status', 'wp-erp-app-helper' ),
            'actions'     => __( 'Actions', 'wp-erp-app-helper' ),
        ];
    }

    protected function get_views() {
        global $wpdb;

        $counts = $wpdb->get_results(
            "SELECT status, COUNT(*) as count FROM {$wpdb->prefix}erp_payment_requests GROUP BY status",
            OBJECT_K
        );

        $pending_count  = isset( $counts['pending'] ) ? (int) $counts['pending']->count : 0;
        $approved_count = isset( $counts['approved'] ) ? (int) $counts['approved']->count : 0;
        $rejected_count = isset( $counts['rejected'] ) ? (int) $counts['rejected']->count : 0;
        $total_count    = $pending_count + $approved_count + $rejected_count;

        $current  = $this->get_selected_status();
        $employee = $this->get_selected_employee_id();
        $base_url = admin_url( 'admin.php?page=erp-hr&section=payment-requests' );

        $query_args = [];
        if ( $employee ) {
            $query_args['employee_id'] = $employee;
        }

        return [
            'all'      => sprintf(
                '<a href="%s" class="%s">%s <span class="count">(%d)</span></a>',
                esc_url( add_query_arg( array_merge( $query_args, [ 'status' => 'all' ] ), $base_url ) ),
                ( 'all' === $current ? 'current' : '' ),
                __( 'All', 'wp-erp-app-helper' ),
                $total_count
            ),
            'pending'  => sprintf(
                '<a href="%s" class="%s">%s <span class="count">(%d)</span></a>',
                esc_url( add_query_arg( array_merge( $query_args, [ 'status' => 'pending' ] ), $base_url ) ),
                ( 'pending' === $current ? 'current' : '' ),
                __( 'Pending', 'wp-erp-app-helper' ),
                $pending_count
            ),
            'approved' => sprintf(
                '<a href="%s" class="%s">%s <span class="count">(%d)</span></a>',
                esc_url( add_query_arg( array_merge( $query_args, [ 'status' => 'approved' ] ), $base_url ) ),
                ( 'approved' === $current ? 'current' : '' ),
                __( 'Approved', 'wp-erp-app-helper' ),
                $approved_count
            ),
            'rejected' => sprintf(
                '<a href="%s" class="%s">%s <span class="count">(%d)</span></a>',
                esc_url( add_query_arg( array_merge( $query_args, [ 'status' => 'rejected' ] ), $base_url ) ),
                ( 'rejected' === $current ? 'current' : '' ),
                __( 'Rejected', 'wp-erp-app-helper' ),
                $rejected_count
            ),
        ];
    }

    protected function extra_tablenav( $which ) {
        if ( 'top' !== $which ) {
            return;
        }

        global $wpdb;

        $selected_employee = $this->get_selected_employee_id();
        $selected_status   = $this->get_selected_status();

        $employees = $wpdb->get_results(
            "SELECT DISTINCT r.employee_id, u.display_name
            FROM {$wpdb->prefix}erp_payment_requests r
            LEFT JOIN {$wpdb->users} u ON r.employee_id = u.ID
            WHERE r.employee_id > 0
            ORDER BY u.display_name ASC"
        );

        echo '<div class="alignleft actions">';
        echo '<label class="screen-reader-text" for="filter-by-employee">' . esc_html__( 'Filter by employee', 'wp-erp-app-helper' ) . '</label>';
        echo '<select name="employee_id" id="filter-by-employee">';
        echo '<option value="0">' . esc_html__( 'All Employees', 'wp-erp-app-helper' ) . '</option>';

        foreach ( $employees as $employee ) {
            /* translators: %d: WordPress user ID */
            $name = ! empty( $employee->display_name ) ? $employee->display_name : sprintf( __( 'User #%d', 'wp-erp-app-helper' ), (int) $employee->employee_id );

            printf(
                '<option value="%1$d" %2$s>%3$s</option>',
                (int) $employee->employee_id,
                selected( $selected_employee, (int) $employee->employee_id, false ),
                esc_html( $name )
            );
        }

        echo '</select>';

        echo '<label class="screen-reader-text" for="filter-by-status">' . esc_html__( 'Filter by status', 'wp-erp-app-helper' ) . '</label>';
        echo '<select name="status" id="filter-by-status">';
        echo '<option value="all" ' . selected( $selected_status, 'all', false ) . '>' . esc_html__( 'All Statuses', 'wp-erp-app-helper' ) . '</option>';
        echo '<option value="pending" ' . selected( $selected_status, 'pending', false ) . '>' . esc_html__( 'Pending', 'wp-erp-app-helper' ) . '</option>';
        echo '<option value="approved" ' . selected( $selected_status, 'approved', false ) . '>' . esc_html__( 'Approved', 'wp-erp-app-helper' ) . '</option>';
        echo '<option value="rejected" ' . selected( $selected_status, 'rejected', false ) . '>' . esc_html__( 'Rejected', 'wp-erp-app-helper' ) . '</option>';
        echo '</select>';

        submit_button( __( 'Filter', 'wp-erp-app-helper' ), 'button', 'filter_action', false );
        echo '</div>';
    }

    public function prepare_items() {
        global $wpdb;

        $columns               = $this->get_columns();
        $this->_column_headers = [ $columns, [], [] ];

        $status      = $this->get_selected_status();
        $employee_id = $this->get_selected_employee_id();

        if ( 'all' === $status && $employee_id > 0 ) {
            $this->items = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT r.*, u.display_name as employee_name
                FROM {$wpdb->prefix}erp_payment_requests r
                LEFT JOIN {$wpdb->users} u ON r.employee_id = u.ID
                WHERE r.employee_id = %d
                ORDER BY r.created_at DESC",
                    $employee_id
                )
            );
        } elseif ( 'all' === $status ) {
            $this->items = $wpdb->get_results(
                "SELECT r.*, u.display_name as employee_name
                FROM {$wpdb->prefix}erp_payment_requests r
                LEFT JOIN {$wpdb->users} u ON r.employee_id = u.ID
                ORDER BY r.created_at DESC"
            );
        } elseif ( $employee_id > 0 ) {
            $this->items = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT r.*, u.display_name as employee_name
                FROM {$wpdb->prefix}erp_payment_requests r
                LEFT JOIN {$wpdb->users} u ON r.employee_id = u.ID
                WHERE r.status = %s AND r.employee_id = %d
                ORDER BY r.created_at DESC",
                    $status,
                    $employee_id
                )
            );
        } else {
            $this->items = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT r.*, u.display_name as employee_name
                FROM {$wpdb->prefix}erp_payment_requests r
                LEFT JOIN {$wpdb->users} u ON r.employee_id = u.ID
                WHERE r.status = %s
                ORDER BY r.created_at DESC",
                    $status
                )
            );
        }
    }

    public function column_default( $item, $column_name ) {
        global $wpdb;

        switch ( $column_name ) {
            case 'employee':
                return '<strong>' . esc_html( $item->employee_name ) . '</strong>';

            case 'title':
                return esc_html( $item->title );

            case 'amount':
                return '<strong>BDT ' . number_format( (float) $item->amount, 2 ) . '</strong>';

            case 'description':
                return esc_html( wp_trim_words( $item->description, 15, '...' ) );

            case 'attachments':
                $attachments = $wpdb->get_results(
                    $wpdb->prepare(
                        "SELECT * FROM {$wpdb->prefix}erp_payment_request_attachments WHERE request_id = %d",
                        $item->id
                    )
                );

                if ( empty( $attachments ) ) {
                    return '—';
                }

                $links = [];
                foreach ( $attachments as $att ) {
                    $url      = wp_get_attachment_url( $att->attachment_id );
                    $filepath = get_attached_file( $att->attachment_id );
                    $filename = $filepath ? basename( $filepath ) : __( 'file', 'wp-erp-app-helper' );
                    if ( $url ) {
                        $links[] = sprintf( '<a href="%s" target="_blank">%s</a>', esc_url( $url ), esc_html( $filename ) );
                    }
                }
                return implode( '<br>', $links );

            case 'expected_payment_date':
                if ( empty( $item->expect_payment_by ) || '0000-00-00' === $item->expect_payment_by ) {
                    return '—';
                }
                return esc_html( date_i18n( get_option( 'date_format' ), strtotime( $item->expect_payment_by ) ) );

            case 'submitted':
                return esc_html( date_i18n( get_option( 'date_format' ), strtotime( $item->created_at ) ) );

            case 'status':
                return sprintf(
                    '<span class="erp-pr-status-badge status-%s">%s</span>',
                    esc_attr( $item->status ),
                    esc_html( ucfirst( $item->status ) )
                );

            case 'actions':
                if ( 'pending' !== $item->status ) {
                    return '—';
                }

                $edit_button   = '';
                $is_hr_manager = current_user_can( 'erp_manage_hr_settings' ) || current_user_can( 'manage_options' );
                $is_hr_creator = $is_hr_manager && ! empty( $item->created_by ) && (int) $item->created_by === get_current_user_id();

                if ( $is_hr_creator ) {
                    $att_rows = $wpdb->get_results(
                        $wpdb->prepare(
                            "SELECT * FROM {$wpdb->prefix}erp_payment_request_attachments WHERE request_id = %d",
                            $item->id
                        )
                    );

                    $attachments = [];
                    foreach ( $att_rows as $att ) {
                        $filepath      = get_attached_file( $att->attachment_id );
                        $attachments[] = [
                            'id'       => (int) $att->attachment_id,
                            'filename' => $filepath ? basename( $filepath ) : '',
                            'url'      => wp_get_attachment_url( $att->attachment_id ),
                        ];
                    }

                    $request_data = wp_json_encode(
                        [
                            'id'                => (int) $item->id,
                            'employee_id'       => (int) $item->employee_id,
                            'title'             => $item->title,
                            'amount'            => (float) $item->amount,
                            'description'       => $item->description,
                            'purchase_date'     => $item->purchase_date,
                            'expect_payment_by' => $item->expect_payment_by,
                            'attachments'       => $attachments,
                        ]
                    );

                    $edit_button = sprintf(
                        '<button type="button" class="button erp-pr-edit-btn" data-request="%s">%s</button> ',
                        esc_attr( $request_data ),
                        __( 'Edit', 'wp-erp-app-helper' )
                    );
                }

                return sprintf(
                    $edit_button .
                    '<button type="button" class="button button-primary erp-pr-approve-btn" data-id="%d" data-title="%s" data-employee="%s" data-amount="%s">%s</button> ' .
                    '<button type="button" class="button erp-pr-reject-btn" data-id="%d" data-title="%s" data-employee="%s">%s</button>',
                    $item->id,
                    esc_attr( $item->title ),
                    esc_attr( $item->employee_name ),
                    esc_attr( number_format( (float) $item->amount, 2 ) ),
                    __( 'Approve', 'wp-erp-app-helper' ),
                    $item->id,
                    esc_attr( $item->title ),
                    esc_attr( $item->employee_name ),
                    __( 'Reject', 'wp-erp-app-helper' )
                );

            default:
                return '';
        }
    }
}
