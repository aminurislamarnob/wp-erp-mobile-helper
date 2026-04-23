<?php
namespace WeLabs\WpErpAppHelper;

if ( ! class_exists( 'WP_List_Table' ) ) {
    require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

/**
 * PaymentRequestListTable — HR view of all payment requests
 */
class PaymentRequestListTable extends \WP_List_Table {

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

        $current  = isset( $_GET['status'] ) ? sanitize_text_field( $_GET['status'] ) : 'pending'; // phpcs:ignore
        $base_url = admin_url( 'admin.php?page=erp-hr&section=payment-requests' );

        return [
            'pending'  => sprintf(
                '<a href="%s" class="%s">%s <span class="count">(%d)</span></a>',
                esc_url( add_query_arg( 'status', 'pending', $base_url ) ),
                ( 'pending' === $current ? 'current' : '' ),
                __( 'Pending', 'wp-erp-app-helper' ),
                $pending_count
            ),
            'approved' => sprintf(
                '<a href="%s" class="%s">%s <span class="count">(%d)</span></a>',
                esc_url( add_query_arg( 'status', 'approved', $base_url ) ),
                ( 'approved' === $current ? 'current' : '' ),
                __( 'Approved', 'wp-erp-app-helper' ),
                $approved_count
            ),
            'rejected' => sprintf(
                '<a href="%s" class="%s">%s <span class="count">(%d)</span></a>',
                esc_url( add_query_arg( 'status', 'rejected', $base_url ) ),
                ( 'rejected' === $current ? 'current' : '' ),
                __( 'Rejected', 'wp-erp-app-helper' ),
                $rejected_count
            ),
        ];
    }

    public function prepare_items() {
        global $wpdb;

        $columns               = $this->get_columns();
        $this->_column_headers = [ $columns, [], [] ];

        $status = isset( $_GET['status'] ) ? sanitize_text_field( $_GET['status'] ) : 'pending'; // phpcs:ignore

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
                return sprintf(
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
