<?php
namespace WeLabs\WpErpAppHelper;

/**
 * LeaveApprovalManager class
 */
class LeaveApprovalManager {

    /**
     * Constructor
     */
    public function __construct() {
        require_once WP_ERP_APP_HELPER_INC_DIR . '/RequiredApprovalListTable.php';

        add_filter( 'erp_leave_request_employee_name_column', [ $this, 'display_required_approval_status' ], 10, 2 );

        // HR Top Level Menu
        add_action( 'admin_menu', [ $this, 'register_erp_menu' ], 20 );

        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_scripts' ] );
        add_action( 'admin_footer', [ $this, 'render_modal_template' ] );

        // AJAX handlers
        add_action( 'wp_ajax_erp_app_helper_get_team_leads', [ $this, 'get_team_leads' ] );
        add_action( 'wp_ajax_erp_app_helper_save_required_approval', [ $this, 'save_required_approval' ] );
        add_action( 'wp_ajax_erp_app_helper_process_leave_action', [ $this, 'process_leave_action' ] );

        // REST API Injections
        add_filter( 'rest_request_after_callbacks', [ $this, 'inject_rest_approval_data' ], 10, 3 );
    }

    /**
     * Display the required approval status badge in the employee name column
     *
     * @param string $content
     * @param int    $request_id
     * @return string
     */
    public function display_required_approval_status( $content, $request_id ) {
        global $wpdb;
        $request = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT required_approver, approval_status FROM {$wpdb->prefix}erp_hr_leave_requests WHERE id = %d",
                $request_id
            )
        );

        if ( ! empty( $request->required_approver ) ) {
            $approver = get_user_by( 'id', $request->required_approver );
            $status   = $request->approval_status ? $request->approval_status : 'Pending';
            $class    = 'status-' . strtolower( $status );

            $content .= sprintf(
                '<div class="erp-app-helper-approval-badge %s" title="%s">%s: %s (%s)</div>',
                esc_attr( $class ),
                esc_attr__( 'Approval required before final approval', 'wp-erp-app-helper' ),
                esc_html__( 'Required Approval', 'wp-erp-app-helper' ),
                esc_html( $approver->display_name ),
                esc_html( $status )
            );
        }

        return $content;
    }

    /**
     * Add "Leave Approval" to Top Level HR Menu
     */
    public function register_erp_menu() {
        if ( current_user_can( 'erp_team_lead' ) || current_user_can( 'erp_project_lead' ) ) {
            erp_add_menu(
                'hr', [
					'title'      => __( 'Leave Approvals', 'wp-erp-app-helper' ),
					'callback'   => [ $this, 'render_approval_list_page' ],
					'slug'       => 'leave-approvals',
					'capability' => 'erp_list_employee',
					'position'   => 4,
				]
            );
        }
    }

    /**
     * Inject approval data into REST API responses
     */
    public function inject_rest_approval_data( $response, $handler, $request ) {
        $route = $request->get_route();
        if ( strpos( $route, '/erp/v1/hrm/leaves/requests' ) === false && strpos( $route, '/erp-app/v1/hrm/employees' ) === false ) {
            return $response;
        }

        $data = $response->get_data();
        if ( empty( $data ) ) {
            return $response;
        }

        // Check if it's a collection or a single item
        $is_collection = isset( $data[0] );
        $items = $is_collection ? $data : [ $data ];

        foreach ( $items as &$item ) {
            // Support both array and object data structures
            $is_item_array = is_array( $item );
            $id = $is_item_array ? ( isset( $item['id'] ) ? $item['id'] : 0 ) : ( isset( $item->id ) ? $item->id : 0 );

            if ( ! $id ) {
                continue;
            }

            global $wpdb;
            $approval = $wpdb->get_row(
                $wpdb->prepare(
                    "SELECT r.required_approver, r.approval_status, u.display_name as approver_name
                FROM {$wpdb->prefix}erp_hr_leave_requests r
                LEFT JOIN wp_users u ON r.required_approver = u.ID
                WHERE r.id = %d",
                    $id
                )
            );

            if ( $approval && $approval->required_approver ) {
                $status = $approval->approval_status ? $approval->approval_status : 'Pending';

                $approval_data = [
                    'approver_id'   => (int) $approval->required_approver,
                    'approver_name' => $approval->approver_name,
                    'status'        => $status,
                ];

                if ( 'Pending' === $status ) {
                    /* translators: %s: approver name */
                    $msg = sprintf(
                        __( 'Waiting for additional approval from %s', 'wp-erp-app-helper' ),
                        $approval->approver_name
                    );
                } else {
                    /* translators: 1: approval status, 2: approver name */
                    $msg = sprintf(
                        __( '%1$s by %2$s', 'wp-erp-app-helper' ),
                        $status,
                        $approval->approver_name
                    );
                }

                if ( $is_item_array ) {
                    $item['required_approval'] = $approval_data;
                    $item['message'] = $msg;
                    $item['required_approval_message'] = $msg;
                } else {
                    $item->required_approval = $approval_data;
                    $item->message = $msg;
                    $item->required_approval_message = $msg;
                }
            } elseif ( $is_item_array ) {
				if ( ! isset( $item['message'] ) ) {
					$item['message'] = '';
				}
                    $item['required_approval'] = null;
                    $item['required_approval_message'] = '';
			} else {
				if ( ! isset( $item->message ) ) {
					$item->message = '';
				}
				$item->required_approval = null;
				$item->required_approval_message = '';
            }
        }

        $response->set_data( $is_collection ? $items : $items[0] );
        return $response;
    }

    /**
     * Render the approval list page
     */
    public function render_approval_list_page() {
        $this->handle_actions();

        $list_table = new RequiredApprovalListTable();
        $list_table->prepare_items();
        ?>
        <div class="wrap">
            <h2><?php esc_html_e( 'Leave Approvals Required', 'wp-erp-app-helper' ); ?></h2>

            <?php $list_table->views(); ?>

            <form method="get" id="erp-app-helper-approval-filter">
                <?php // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only page/section params for hidden form fields. ?>
                <input type="hidden" name="page" value="<?php echo esc_attr( isset( $_REQUEST['page'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['page'] ) ) : '' ); ?>" />
                <?php if ( isset( $_REQUEST['section'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
                    <input type="hidden" name="section" value="<?php echo esc_attr( sanitize_text_field( wp_unslash( $_REQUEST['section'] ) ) ); ?>" />
                <?php endif; ?>
                
                <?php $list_table->display(); ?>
            </form>
        </div>
        <?php
    }

    /**
     * Handle Approve/Reject actions from the list table
     */
    public function handle_actions() {
        if ( ! isset( $_GET['action'] ) || ! isset( $_GET['id'] ) ) {
            return;
        }

        if ( ! wp_verify_nonce( sanitize_key( wp_unslash( $_GET['_wpnonce'] ?? '' ) ), 'erp-app-helper-action' ) ) {
            return;
        }

        $id     = absint( $_GET['id'] );
        $action = sanitize_text_field( wp_unslash( $_GET['action'] ) );
        global $wpdb;

        if ( 'approve' === $action ) {
            $wpdb->update(
                "{$wpdb->prefix}erp_hr_leave_requests",
                [ 'approval_status' => 'Approved' ],
                [ 'id' => $id ],
                [ '%s' ],
                [ '%d' ]
            );

            // Log the approval
            $wpdb->insert(
                "{$wpdb->prefix}erp_hr_leave_approval_status", [
					'leave_request_id'   => $id,
					'approval_status_id' => 2, // Keep it pending for final HR approval
                    /* translators: %s: approver display name */
					'message'            => sprintf( __( 'Approved by required approver (%s)', 'wp-erp-app-helper' ), wp_get_current_user()->display_name ),
					'approved_by'        => get_current_user_id(),
					'created_at'         => time(),
				]
            );

            echo '<div class="updated"><p>' . esc_html__( 'Leave request approved successfully.', 'wp-erp-app-helper' ) . '</p></div>';
        } elseif ( 'reject' === $action ) {
            $wpdb->update(
                "{$wpdb->prefix}erp_hr_leave_requests",
                [ 'approval_status' => 'Rejected' ],
                [ 'id' => $id ],
                [ '%s' ],
                [ '%d' ]
            );

            // Log the rejection
            $wpdb->insert(
                "{$wpdb->prefix}erp_hr_leave_approval_status", [
					'leave_request_id'   => $id,
					'approval_status_id' => 2, // Still pending but flagged as rejected by requester
                    /* translators: %s: approver display name */
					'message'            => sprintf( __( 'Rejected by required approver (%s)', 'wp-erp-app-helper' ), wp_get_current_user()->display_name ),
					'approved_by'        => get_current_user_id(),
					'created_at'         => time(),
				]
            );

            echo '<div class="error"><p>' . esc_html__( 'Leave request rejected.', 'wp-erp-app-helper' ) . '</p></div>';
        }
    }

    /**
     * Enqueue scripts and styles
     */
    public function enqueue_scripts( $hook ) {
        if ( ! isset( $_GET['page'] ) || 'erp-hr' !== $_GET['page'] ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            return;
        }

        wp_enqueue_style( 'erp-app-helper-admin', WP_ERP_APP_HELPER_PLUGIN_ASSET . '/admin/css/admin.css', [], WP_ERP_APP_HELPER_PLUGIN_VERSION );
        wp_enqueue_script( 'erp-app-helper-leave-approval', WP_ERP_APP_HELPER_PLUGIN_ASSET . '/admin/js/leave-approval.js', [ 'jquery', 'erp-script' ], WP_ERP_APP_HELPER_PLUGIN_VERSION, true );

        wp_localize_script(
            'erp-app-helper-leave-approval', 'erpAppHelper', [
				'ajaxurl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'erp-app-helper-nonce' ),
				'i18n'    => [
					'selectClead' => __( 'Select Team Lead or Project Lead', 'wp-erp-app-helper' ),
					'save'        => __( 'Save', 'wp-erp-app-helper' ),
					'cancel'      => __( 'Cancel', 'wp-erp-app-helper' ),
					'loading'     => __( 'Loading...', 'wp-erp-app-helper' ),
					'success'     => __( 'Required approval saved successfully.', 'wp-erp-app-helper' ),
					'approve'     => __( 'Approve', 'wp-erp-app-helper' ),
					'reject'      => __( 'Reject', 'wp-erp-app-helper' ),
					'approveTitle' => __( 'Approve Leave Request', 'wp-erp-app-helper' ),
					'rejectTitle'  => __( 'Reject Leave Request', 'wp-erp-app-helper' ),
					'approveLabel' => __( 'Approval Message (Optional)', 'wp-erp-app-helper' ),
					'rejectLabel'  => __( 'Rejection Reason', 'wp-erp-app-helper' ),
					'actionSuccess' => __( 'Action processed successfully.', 'wp-erp-app-helper' ),
				],
			]
        );
    }

    /**
     * Render the modal template
     */
    public function render_modal_template() {
        if ( ! isset( $_GET['page'] ) || 'erp-hr' !== $_GET['page'] ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            return;
        }

        welabs_wp_erp_app_helper()->get_template( 'leave-approval-modal.php' );
        welabs_wp_erp_app_helper()->get_template( 'leave-action-modal.php' );
    }

    /**
     * Get employees with Team Lead or higher capability
     */
    public function get_team_leads() {
        check_ajax_referer( 'erp-app-helper-nonce', 'nonce' );

        $users = get_users(
            [
				'role__in' => [ 'erp_team_lead', 'erp_project_lead' ],
				'fields'   => [ 'ID', 'display_name' ],
			]
        );

        $results = [];
        foreach ( $users as $user ) {
            $results[] = [
                'id' => $user->ID,
                'name' => $user->display_name,
            ];
        }

        wp_send_json_success( $results );
    }

    /**
     * Save the required approval setting
     */
    public function save_required_approval() {
        check_ajax_referer( 'erp-app-helper-nonce', 'nonce' );

        $request_id = isset( $_POST['request_id'] ) ? absint( $_POST['request_id'] ) : 0;
        $approver_id = isset( $_POST['approver_id'] ) ? absint( $_POST['approver_id'] ) : 0;

        if ( ! $request_id || ! $approver_id ) {
            wp_send_json_error( __( 'Invalid request or approver.', 'wp-erp-app-helper' ) );
        }

        // Save to the leave request record
        global $wpdb;
        $wpdb->update(
            "{$wpdb->prefix}erp_hr_leave_requests",
            [
                'required_approver' => $approver_id,
                'approval_status'   => 'Pending',
            ],
            [ 'id' => $request_id ],
            [ '%d', '%s' ],
            [ '%d' ]
        );

        // Also add a status message to the leave request history
        $approver = get_user_by( 'id', $approver_id );
        /* translators: %s: approver display name */
        $message = sprintf( __( 'Waiting for additional approval from %s', 'wp-erp-app-helper' ), $approver->display_name );

        $wpdb->insert(
            "{$wpdb->prefix}erp_hr_leave_approval_status", [
				'leave_request_id'   => $request_id,
				'approval_status_id' => 2, // Keep it pending
				'message'            => $message,
				'approved_by'        => get_current_user_id(),
				'created_at'         => time(),
			]
        );

        wp_send_json_success();
    }
    /**
     * AJAX: Process Approve/Reject from the approver screen
     */
    public function process_leave_action() {
        check_ajax_referer( 'erp-app-helper-nonce', 'nonce' );

        $request_id = isset( $_POST['request_id'] ) ? absint( $_POST['request_id'] ) : 0;
        $action      = isset( $_POST['action_type'] ) ? sanitize_key( $_POST['action_type'] ) : ''; // 'approve' or 'reject'
        $message     = isset( $_POST['message'] ) ? sanitize_textarea_field( wp_unslash( $_POST['message'] ) ) : '';

        if ( ! $request_id || ! in_array( $action, [ 'approve', 'reject' ], true ) ) {
            wp_send_json_error( __( 'Invalid request.', 'wp-erp-app-helper' ) );
        }

        if ( 'reject' === $action && empty( $message ) ) {
            wp_send_json_error( __( 'Please provide a reason for rejection.', 'wp-erp-app-helper' ) );
        }

        global $wpdb;
        $status = ( 'approve' === $action ) ? 'Approved' : 'Rejected';

        $updated = $wpdb->update(
            "{$wpdb->prefix}erp_hr_leave_requests",
            [
                'approval_status' => $status,
                'approval_message' => $message,
            ],
            [ 'id' => $request_id ],
            [ '%s', '%s' ],
            [ '%d' ]
        );

        if ( false === $updated ) {
            wp_send_json_error( __( 'Failed to update request.', 'wp-erp-app-helper' ) );
        }

        // Add history log using raw query to avoid Model issues if not loaded
        $wpdb->insert(
            "{$wpdb->prefix}erp_hr_leave_approval_status", [
				'leave_request_id'   => $request_id,
				'approval_status_id' => ( 'approve' === $action ) ? 1 : 3, // 1: Approved, 3: Rejected
                /* translators: 1: status (Approved/Rejected), 2: approver name, 3: reason message */
				'message'            => sprintf(
					__( '%1$s by required approver (%2$s): %3$s', 'wp-erp-app-helper' ),
					ucfirst( $status ),
					wp_get_current_user()->display_name,
					$message
				),
				'approved_by'        => get_current_user_id(),
				'created_at'         => time(),
			]
        );

        wp_send_json_success( __( 'Action processed successfully.', 'wp-erp-app-helper' ) );
    }
}
