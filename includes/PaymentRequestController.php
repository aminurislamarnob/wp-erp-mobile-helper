<?php
namespace WeLabs\WpErpAppHelper;

use WP_REST_Server;
use WP_REST_Request;
use WP_REST_Response;
use WP_Error;

/**
 * PaymentRequestController — REST API endpoints under erp-app/v1
 */
class PaymentRequestController {

    protected $namespace = 'erp-app/v1';

    /**
     * Cache for existence of created_by column in payment requests table.
     *
     * @var bool|null
     */
    private $has_created_by_column = null;

    /**
     * Allowed attachment MIME types
     */
    const ALLOWED_MIME_TYPES = [ 'application/pdf', 'image/jpeg', 'image/png' ];

    /**
     * Maximum file size in bytes (10 MB)
     */
    const MAX_FILE_SIZE = 10485760;

    public function register_routes() {
        // Employee endpoints
        register_rest_route(
            $this->namespace, '/payment-requests', [
				[
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => [ $this, 'create_request' ],
                    'permission_callback' => [ $this, 'create_permission' ],
				],
				[
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => [ $this, 'get_own_requests' ],
					'permission_callback' => [ $this, 'employee_permission' ],
				],
			]
        );

        register_rest_route(
            $this->namespace, '/payment-requests/(?P<id>[\d]+)', [
				[
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => [ $this, 'get_single_request' ],
					'permission_callback' => [ $this, 'employee_permission' ],
				],
			]
        );

        // HR endpoints
        register_rest_route(
            $this->namespace, '/hr/payment-requests', [
				[
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => [ $this, 'hr_get_requests' ],
					'permission_callback' => [ $this, 'hr_permission' ],
				],
			]
        );

        register_rest_route(
            $this->namespace, '/hr/payment-requests/(?P<id>[\d]+)/review', [
				[
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => [ $this, 'hr_review_request' ],
					'permission_callback' => [ $this, 'hr_permission' ],
				],
			]
        );

        register_rest_route(
            $this->namespace, '/currency', [
				[
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => [ $this, 'get_currency' ],
					'permission_callback' => [ $this, 'employee_permission' ],
				],
			]
        );
    }

    // ── Permission callbacks ───────────────────────────────────────────────

    public function employee_permission() {
        return current_user_can( 'erp_list_employee' );
    }

    public function create_permission() {
        return current_user_can( 'erp_list_employee' ) || $this->hr_permission();
    }

    public function hr_permission() {
        return current_user_can( 'erp_manage_hr_settings' ) || current_user_can( 'manage_options' );
    }

    // ── Employee endpoints ─────────────────────────────────────────────────

    /**
     * POST /payment-requests — submit a new request
     */
    public function create_request( WP_REST_Request $request ) {
        if ( ! $this->maybe_add_created_by_column() ) {
            return new WP_Error( 'schema_init_failed', __( 'Failed to initialize request metadata. Please try again.', 'wp-erp-app-helper' ), [ 'status' => 500 ] );
        }

        $is_hr_manager     = $this->hr_permission();
        $title             = sanitize_text_field( $request->get_param( 'title' ) );
        $amount            = (float) $request->get_param( 'amount' );
        $description       = sanitize_textarea_field( $request->get_param( 'description' ) );
        $purchase_date     = sanitize_text_field( $request->get_param( 'purchase_date' ) );
        $expect_payment_by = sanitize_text_field( $request->get_param( 'expect_payment_by' ) );
        $attachment_ids    = array_map( 'absint', (array) $request->get_param( 'attachment_ids' ) );

        if ( empty( $title ) ) {
            return new WP_Error( 'missing_title', __( 'Title is required.', 'wp-erp-app-helper' ), [ 'status' => 400 ] );
        }
        if ( $amount <= 0 ) {
            return new WP_Error( 'invalid_amount', __( 'Amount must be greater than zero.', 'wp-erp-app-helper' ), [ 'status' => 400 ] );
        }
        if ( empty( $description ) ) {
            return new WP_Error( 'missing_description', __( 'Description is required.', 'wp-erp-app-helper' ), [ 'status' => 400 ] );
        }
        if ( empty( $attachment_ids ) ) {
            return new WP_Error( 'missing_attachments', __( 'At least one attachment is required.', 'wp-erp-app-helper' ), [ 'status' => 400 ] );
        }

        $validation = $this->validate_attachments( $attachment_ids, $is_hr_manager );
        if ( is_wp_error( $validation ) ) {
            return new WP_Error( $validation->get_error_code(), $validation->get_error_message(), [ 'status' => 400 ] );
        }

        $employee_id = get_current_user_id();
        if ( $is_hr_manager ) {
            $employee_id = absint( $request->get_param( 'employee_id' ) );
            if ( ! $employee_id || ! get_userdata( $employee_id ) ) {
                return new WP_Error( 'invalid_employee', __( 'Please select a valid employee.', 'wp-erp-app-helper' ), [ 'status' => 400 ] );
            }
        }

        global $wpdb;
        $now = current_time( 'mysql' );

        $wpdb->insert(
            "{$wpdb->prefix}erp_payment_requests",
            [
                'employee_id'       => $employee_id,
                'created_by'        => get_current_user_id(),
                'title'             => $title,
                'amount'            => $amount,
                'description'       => $description,
                'purchase_date'     => $purchase_date,
                'expect_payment_by' => $expect_payment_by,
                'status'            => 'pending',
                'created_at'        => $now,
                'updated_at'        => $now,
            ],
            [ '%d', '%d', '%s', '%f', '%s', '%s', '%s', '%s', '%s', '%s' ]
        );

        if ( ! $wpdb->insert_id ) {
            return new WP_Error( 'insert_failed', __( 'Failed to save request.', 'wp-erp-app-helper' ), [ 'status' => 500 ] );
        }

        $request_id = $wpdb->insert_id;

        foreach ( $attachment_ids as $att_id ) {
            $mime = get_post_mime_type( $att_id );
            $type = $this->mime_to_short_type( $mime );
            $wpdb->insert(
                "{$wpdb->prefix}erp_payment_request_attachments",
                [
                    'request_id'    => $request_id,
                    'attachment_id' => $att_id,
                    'file_type'     => $type,
                ],
                [ '%d', '%d', '%s' ]
            );
        }

        $record = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}erp_payment_requests WHERE id = %d",
                $request_id
            )
        );

        return new WP_REST_Response( $this->format_request_response( $record ), 201 );
    }

    /**
     * GET /payment-requests — list employee's own requests
     */
    public function get_own_requests( WP_REST_Request $request ) {
        global $wpdb;

        // Touch the request object so the callback signature remains explicit.
        $request->get_route();

        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}erp_payment_requests WHERE employee_id = %d ORDER BY created_at DESC",
                get_current_user_id()
            )
        );

        $data = array_map( [ $this, 'format_request_response' ], $rows );

        return rest_ensure_response( [
            'currency' => erp_get_currency(),
            'data'     => $data,
        ] );
    }

    /**
     * GET /currency — active ERP currency
     */
    public function get_currency( WP_REST_Request $request ) {
        $request->get_route();

        return rest_ensure_response( erp_get_currency() );
    }

    /**
     * GET /payment-requests/{id} — single request (own only)
     */
    public function get_single_request( WP_REST_Request $request ) {
        global $wpdb;

        $id     = (int) $request['id'];
        $record = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}erp_payment_requests WHERE id = %d",
                $id
            )
        );

        if ( ! $record ) {
            return new WP_Error( 'not_found', __( 'Request not found.', 'wp-erp-app-helper' ), [ 'status' => 404 ] );
        }

        if ( (int) $record->employee_id !== get_current_user_id() ) {
            return new WP_Error( 'forbidden', __( 'You do not have permission to view this request.', 'wp-erp-app-helper' ), [ 'status' => 403 ] );
        }

        return rest_ensure_response( $this->format_request_response( $record ) );
    }

    // ── HR endpoints ───────────────────────────────────────────────────────

    /**
     * GET /hr/payment-requests — list all requests with optional ?status= filter
     */
    public function hr_get_requests( WP_REST_Request $request ) {
        global $wpdb;

        $status = $request->get_param( 'status' );

        if ( $status ) {
            $rows = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT r.*, u.display_name as employee_name
                FROM {$wpdb->prefix}erp_payment_requests r
                LEFT JOIN {$wpdb->users} u ON r.employee_id = u.ID
                WHERE r.status = %s
                ORDER BY r.created_at DESC",
                    sanitize_text_field( $status )
                )
            );
        } else {
            $rows = $wpdb->get_results(
                "SELECT r.*, u.display_name as employee_name
                FROM {$wpdb->prefix}erp_payment_requests r
                LEFT JOIN {$wpdb->users} u ON r.employee_id = u.ID
                ORDER BY r.created_at DESC"
            );
        }

        $data = array_map(
            function ( $row ) {
                $formatted                  = $this->format_request_response( $row );
                $formatted['employee_name'] = isset( $row->employee_name ) ? $row->employee_name : '';
                return $formatted;
            }, $rows
        );

        return rest_ensure_response( [
            'currency' => erp_get_currency(),
            'data'     => $data,
        ] );
    }

    /**
     * POST /hr/payment-requests/{id}/review — approve or reject
     */
    public function hr_review_request( WP_REST_Request $request ) {
        global $wpdb;

        $id           = (int) $request['id'];
        $action_type  = sanitize_key( $request->get_param( 'action' ) );
        $hr_note      = sanitize_textarea_field( $request->get_param( 'hr_note' ) );
        $payment_type = sanitize_key( $request->get_param( 'payment_type' ) );

        if ( ! in_array( $action_type, [ 'approve', 'reject' ], true ) ) {
            return new WP_Error( 'invalid_action', __( 'Action must be "approve" or "reject".', 'wp-erp-app-helper' ), [ 'status' => 400 ] );
        }

        if ( 'approve' === $action_type && ! in_array( $payment_type, [ 'cash', 'bank_transfer' ], true ) ) {
            return new WP_Error( 'missing_payment_type', __( 'Payment type is required when approving.', 'wp-erp-app-helper' ), [ 'status' => 400 ] );
        }

        if ( 'reject' === $action_type && empty( $hr_note ) ) {
            return new WP_Error( 'missing_note', __( 'A rejection note is required.', 'wp-erp-app-helper' ), [ 'status' => 400 ] );
        }

        $record = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}erp_payment_requests WHERE id = %d",
                $id
            )
        );

        if ( ! $record ) {
            return new WP_Error( 'not_found', __( 'Request not found.', 'wp-erp-app-helper' ), [ 'status' => 404 ] );
        }

        if ( 'pending' !== $record->status ) {
            return new WP_Error( 'invalid_status', __( 'Only pending requests can be reviewed.', 'wp-erp-app-helper' ), [ 'status' => 400 ] );
        }

        $new_status = ( 'approve' === $action_type ) ? 'approved' : 'rejected';
        $now        = current_time( 'mysql' );

        $wpdb->update(
            "{$wpdb->prefix}erp_payment_requests",
            [
                'status'       => $new_status,
                'hr_note'      => $hr_note,
                'payment_type' => ( 'approved' === $new_status ) ? $payment_type : null,
                'reviewed_by'  => get_current_user_id(),
                'reviewed_at'  => $now,
                'updated_at'   => $now,
            ],
            [ 'id' => $id ],
            [ '%s', '%s', '%s', '%d', '%s', '%s' ],
            [ '%d' ]
        );

        $updated = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}erp_payment_requests WHERE id = %d",
                $id
            )
        );

        $manager = welabs_wp_erp_app_helper()->payment_requests;
        if ( $manager && method_exists( $manager, 'send_notification' ) ) {
            $manager->send_notification( $updated, $new_status );
        }

        return rest_ensure_response( $this->format_request_response( $updated ) );
    }

    // ── Helpers ────────────────────────────────────────────────────────────

    /**
     * Build the standard response shape for a single request object
     */
    public function format_request_response( $record ) {
        global $wpdb;

        $reviewed_by = null;
        if ( ! empty( $record->reviewed_by ) ) {
            $reviewer    = get_userdata( $record->reviewed_by );
            $reviewed_by = $reviewer ? [
				'id' => (int) $record->reviewed_by,
				'name' => $reviewer->display_name,
			] : null;
        }

        $attachment_rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}erp_payment_request_attachments WHERE request_id = %d",
                $record->id
            )
        );

        $attachments = [];
        foreach ( $attachment_rows as $att ) {
            $url      = wp_get_attachment_url( $att->attachment_id );
            $filepath = get_attached_file( $att->attachment_id );
            $filename = $filepath ? basename( $filepath ) : '';
            $mime     = get_post_mime_type( $att->attachment_id );

            $attachments[] = [
                'id'       => (int) $att->attachment_id,
                'url'      => $url ? $url : '',
                'type'     => $this->mime_to_short_type( $mime ),
                'filename' => $filename,
            ];
        }

        return [
            'id'                => (int) $record->id,
            'title'             => $record->title,
            'amount'            => number_format( (float) $record->amount, 2, '.', '' ),
            'description'       => $record->description,
            'purchase_date'     => $record->purchase_date ? $record->purchase_date : null,
            'expect_payment_by' => $record->expect_payment_by ? $record->expect_payment_by : null,
            'status'            => $record->status,
            'payment_type'      => $record->payment_type ? $record->payment_type : null,
            'hr_note'           => $record->hr_note ? $record->hr_note : null,
            'reviewed_by'       => $reviewed_by,
            'reviewed_at'       => $record->reviewed_at,
            'created_at'        => $record->created_at,
            'attachments'       => $attachments,
        ];
    }

    /**
     * Validate attachment IDs: existence, ownership, MIME type, and file size
     *
     * @return true|\WP_Error
     */
    public function validate_attachments( array $attachment_ids, $allow_non_owner = false ) {
        $current_user_id = get_current_user_id();

        foreach ( $attachment_ids as $att_id ) {
            $post = get_post( $att_id );

            if ( ! $post || 'attachment' !== $post->post_type ) {
                /* translators: %d: attachment ID */
                return new WP_Error( 'invalid_attachment', sprintf( __( 'Attachment %d not found.', 'wp-erp-app-helper' ), $att_id ) );
            }

            if ( ! $allow_non_owner && (int) $post->post_author !== $current_user_id ) {
                /* translators: %d: attachment ID */
                return new WP_Error( 'attachment_ownership', sprintf( __( 'Attachment %d does not belong to you.', 'wp-erp-app-helper' ), $att_id ) );
            }

            $mime = get_post_mime_type( $att_id );
            if ( ! in_array( $mime, self::ALLOWED_MIME_TYPES, true ) ) {
                /* translators: 1: attachment ID, 2: MIME type */
                return new WP_Error( 'invalid_mime', sprintf( __( 'Attachment %1$d has an unsupported file type (%2$s). Only PDF, JPG, and PNG are allowed.', 'wp-erp-app-helper' ), $att_id, $mime ) );
            }

            $filepath = get_attached_file( $att_id );
            if ( $filepath && file_exists( $filepath ) && filesize( $filepath ) > self::MAX_FILE_SIZE ) {
                /* translators: %d: attachment ID */
                return new WP_Error( 'file_too_large', sprintf( __( 'Attachment %d exceeds the 10 MB size limit.', 'wp-erp-app-helper' ), $att_id ) );
            }
        }

        return true;
    }

    /**
     * Convert MIME type to short file type string
     */
    private function mime_to_short_type( $mime ) {
        $map = [
            'application/pdf' => 'pdf',
            'image/jpeg'      => 'jpg',
            'image/png'       => 'png',
        ];
        return isset( $map[ $mime ] ) ? $map[ $mime ] : 'file';
    }

    /**
     * Ensure payment request table has created_by column for creator-level permissions.
     */
    private function maybe_add_created_by_column() {
        if ( $this->has_created_by_column() ) {
            return true;
        }

        global $wpdb;
        $table_name = "{$wpdb->prefix}erp_payment_requests";

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.SchemaChange -- DDL ALTER TABLE requires interpolation for identifiers.
        $result = $wpdb->query( "ALTER TABLE `$table_name` ADD `created_by` bigint(20) UNSIGNED DEFAULT NULL" );

        if ( false === $result ) {
            return false;
        }

        $this->has_created_by_column = true;
        return true;
    }

    /**
     * Check if payment request table has created_by column.
     */
    private function has_created_by_column() {
        if ( null !== $this->has_created_by_column ) {
            return $this->has_created_by_column;
        }

        global $wpdb;
        $table_name = "{$wpdb->prefix}erp_payment_requests";

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table/column identifiers cannot be placeholders.
        $row = $wpdb->get_results( "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE table_name = '$table_name' AND column_name = 'created_by'" );

        $this->has_created_by_column = ! empty( $row );
        return $this->has_created_by_column;
    }
}
