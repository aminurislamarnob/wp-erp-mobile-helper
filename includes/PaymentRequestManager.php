<?php
namespace WeLabs\WpErpAppHelper;

/**
 * PaymentRequestManager — admin UI, menus, AJAX handlers, and notifications
 */
class PaymentRequestManager {

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

    public function __construct() {
        require_once WP_ERP_APP_HELPER_INC_DIR . '/PaymentRequestListTable.php';

        add_action( 'admin_menu', [ $this, 'register_erp_menu' ], 20 );
        add_filter( 'erp_hr_employee_single_tabs', [ $this, 'add_profile_tab' ], 10, 2 );
        add_action( 'erp_hr_leave_calendar_actions', [ $this, 'render_leave_calendar_button' ] );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_scripts' ] );
        add_action( 'admin_footer', [ $this, 'render_modal_template' ] );

        add_action( 'wp_ajax_erp_app_helper_submit_payment_request', [ $this, 'ajax_submit_payment_request' ] );
        add_action( 'wp_ajax_erp_app_helper_update_payment_request', [ $this, 'ajax_update_payment_request' ] );
        add_action( 'wp_ajax_erp_app_helper_review_payment_request', [ $this, 'ajax_review_payment_request' ] );
    }

    /**
     * Register ERP HR submenus
     */
    public function register_erp_menu() {
        erp_add_menu(
            'hr', [
				'title'      => __( 'Payment Requests', 'wp-erp-app-helper' ),
				'callback'   => [ $this, 'render_hr_list_page' ],
				'slug'       => 'payment-requests',
				'capability' => 'erp_manage_hr_settings',
				'position'   => 5,
			]
        );
    }

    /**
     * Inject "Bill Requests" tab into the employee profile navigation.
     * Visible to the employee on their own profile, and to HR managers on any profile.
     */
    public function add_profile_tab( $tabs, $employee ) {
        $is_own_profile = absint( $employee->get_user_id() ) === get_current_user_id();
        $is_hr_manager  = current_user_can( 'erp_manage_hr_settings' );

        if ( ! $is_own_profile && ! $is_hr_manager ) {
            return $tabs;
        }

        $tabs['bill-requests'] = [
            'title'    => __( 'Bill Requests', 'wp-erp-app-helper' ),
            'callback' => [ $this, 'render_profile_tab' ],
        ];

        return $tabs;
    }

    /**
     * Render the "New Bill Request" button inside the leave calendar actions wrap.
     */
    public function render_leave_calendar_button() {
        if ( ! current_user_can( 'erp_list_employee' ) ) {
            return;
        }

        if ( current_user_can( 'erp_manage_hr_settings' ) || current_user_can( 'manage_options' ) ) {
            return;
        }
        ?>
        <div class="erp-hr-bill-request-wrap">
            <a href="#" class="button button-primary" id="erp-pr-open-form-modal">
                <?php esc_html_e( 'New Bill Request', 'wp-erp-app-helper' ); ?>
            </a>
        </div>
        <?php
    }

    /**
     * Render the "Bill Requests" profile tab for the given employee.
     */
    public function render_profile_tab( $employee ) {
        global $wpdb;
        $employee_id    = $employee->get_user_id();
        $profile_url    = erp_hr_url_single_employee( $employee_id );
        $base_url       = add_query_arg( 'tab', 'bill-requests', $profile_url );
        $current_status = isset( $_GET['status'] ) ? sanitize_text_field( $_GET['status'] ) : 'all'; // phpcs:ignore

        $counts_raw = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT status, COUNT(*) as count FROM {$wpdb->prefix}erp_payment_requests WHERE employee_id = %d GROUP BY status",
                $employee_id
            ), OBJECT_K
        );

        $total = (int) array_sum( array_column( $counts_raw, 'count' ) );

        if ( 'all' === $current_status ) {
            $requests = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT * FROM {$wpdb->prefix}erp_payment_requests WHERE employee_id = %d ORDER BY created_at DESC",
                    $employee_id
                )
            );
        } else {
            $requests = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT * FROM {$wpdb->prefix}erp_payment_requests WHERE employee_id = %d AND status = %s ORDER BY created_at DESC",
                    $employee_id, $current_status
                )
            );
        }

        $statuses = [
            'all'      => __( 'All', 'wp-erp-app-helper' ),
            'pending'  => __( 'Pending', 'wp-erp-app-helper' ),
            'approved' => __( 'Approved', 'wp-erp-app-helper' ),
            'rejected' => __( 'Rejected', 'wp-erp-app-helper' ),
        ];

        $attachments_by_request = [];
        if ( ! empty( $requests ) ) {
            $ids      = implode( ',', array_map( 'intval', array_column( $requests, 'id' ) ) );
            $att_rows = $wpdb->get_results( "SELECT * FROM {$wpdb->prefix}erp_payment_request_attachments WHERE request_id IN ($ids)" ); // phpcs:ignore
            foreach ( $att_rows as $att ) {
                $filepath = get_attached_file( $att->attachment_id );
                $attachments_by_request[ $att->request_id ][] = [
                    'id'       => (int) $att->attachment_id,
                    'filename' => $filepath ? basename( $filepath ) : '',
                    'url'      => wp_get_attachment_url( $att->attachment_id ),
                ];
            }
        }

        welabs_wp_erp_app_helper()->get_template(
            'my-bill-requests.php', [
				'base_url'               => $base_url,
				'current_status'         => $current_status,
				'statuses'               => $statuses,
				'total'                  => $total,
				'counts_raw'             => $counts_raw,
				'requests'               => $requests,
				'attachments_by_request' => $attachments_by_request,
			]
        );
    }

    /**
     * Render HR payment request queue
     */
    public function render_hr_list_page() {
        $list_table = new PaymentRequestListTable();
        $list_table->prepare_items();
        ?>
        <div class="wrap">
            <h2>
                <?php esc_html_e( 'Payment Requests', 'wp-erp-app-helper' ); ?>
                <a href="#" class="page-title-action" id="erp-pr-open-form-modal">
                    <?php esc_html_e( 'Add New', 'wp-erp-app-helper' ); ?>
                </a>
            </h2>

            <?php $list_table->views(); ?>

            <form method="get" id="erp-pr-hr-filter">
                <input type="hidden" name="page" value="<?php echo esc_attr( $_REQUEST['page'] ); // phpcs:ignore ?>" />
                <input type="hidden" name="section" value="payment-requests" />
                <?php $list_table->display(); ?>
            </form>
        </div>
        <?php
    }

    /**
     * Render employee bill requests page (list or submission form)
     */
    public function render_employee_page() {
        global $wpdb;
        $current_user_id = get_current_user_id();
        $base_url        = admin_url( 'admin.php?page=erp-hr&section=my-bill-requests' );
        $current_status  = isset( $_GET['status'] ) ? sanitize_text_field( $_GET['status'] ) : 'all'; // phpcs:ignore

        $counts_raw = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT status, COUNT(*) as count FROM {$wpdb->prefix}erp_payment_requests WHERE employee_id = %d GROUP BY status",
                $current_user_id
            ), OBJECT_K
        );

        $total = (int) array_sum( array_column( $counts_raw, 'count' ) );

        if ( 'all' === $current_status ) {
            $requests = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT * FROM {$wpdb->prefix}erp_payment_requests WHERE employee_id = %d ORDER BY created_at DESC",
                    $current_user_id
                )
            );
        } else {
            $requests = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT * FROM {$wpdb->prefix}erp_payment_requests WHERE employee_id = %d AND status = %s ORDER BY created_at DESC",
                    $current_user_id, $current_status
                )
            );
        }

        $statuses = [
            'all'      => __( 'All', 'wp-erp-app-helper' ),
            'pending'  => __( 'Pending', 'wp-erp-app-helper' ),
            'approved' => __( 'Approved', 'wp-erp-app-helper' ),
            'rejected' => __( 'Rejected', 'wp-erp-app-helper' ),
        ];

        // Fetch all attachments for the displayed requests in one query
        $attachments_by_request = [];
        if ( ! empty( $requests ) ) {
            $ids         = implode( ',', array_map( 'intval', array_column( $requests, 'id' ) ) );
            $att_rows    = $wpdb->get_results( "SELECT * FROM {$wpdb->prefix}erp_payment_request_attachments WHERE request_id IN ($ids)" ); // phpcs:ignore
            foreach ( $att_rows as $att ) {
                $filepath = get_attached_file( $att->attachment_id );
                $attachments_by_request[ $att->request_id ][] = [
                    'id'       => (int) $att->attachment_id,
                    'filename' => $filepath ? basename( $filepath ) : '',
                    'url'      => wp_get_attachment_url( $att->attachment_id ),
                ];
            }
        }

        welabs_wp_erp_app_helper()->get_template(
            'my-bill-requests.php', [
				'base_url'               => $base_url,
				'current_status'         => $current_status,
				'statuses'               => $statuses,
				'total'                  => $total,
				'counts_raw'             => $counts_raw,
				'requests'               => $requests,
				'attachments_by_request' => $attachments_by_request,
			]
        );
    }

    /**
     * Enqueue scripts on relevant admin pages
     */
    public function enqueue_scripts() {
        $page    = isset( $_GET['page'] ) ? sanitize_text_field( $_GET['page'] ) : ''; // phpcs:ignore
        $section = isset( $_GET['section'] ) ? sanitize_text_field( $_GET['section'] ) : ''; // phpcs:ignore
        $action  = isset( $_GET['action'] ) ? sanitize_text_field( $_GET['action'] ) : ''; // phpcs:ignore
        $tab     = isset( $_GET['tab'] ) ? sanitize_text_field( $_GET['tab'] ) : ''; // phpcs:ignore
        $id      = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0; // phpcs:ignore

        $is_hr_queue    = 'erp-hr' === $page && 'payment-requests' === $section;
        $is_profile_tab = 'erp-hr' === $page && 'view' === $action && 'bill-requests' === $tab;
        $is_dashboard   = 'erp-hr' === $page && in_array( $section, [ 'dashboard', '' ], true );

        if ( ! $is_hr_queue && ! $is_profile_tab && ! $is_dashboard ) {
            return;
        }

        wp_enqueue_style( 'erp-app-helper-admin', WP_ERP_APP_HELPER_PLUGIN_ASSET . '/admin/css/admin.css', [], WP_ERP_APP_HELPER_PLUGIN_VERSION );

        wp_enqueue_media();

        wp_enqueue_script(
            'erp-app-helper-payment-request',
            WP_ERP_APP_HELPER_PLUGIN_ASSET . '/admin/js/payment-request.js',
            [ 'jquery', 'jquery-ui-datepicker' ],
            WP_ERP_APP_HELPER_PLUGIN_VERSION,
            true
        );

        if ( $is_profile_tab && $id ) {
            $profile_url = erp_hr_url_single_employee( $id );
            $list_url    = add_query_arg( 'tab', 'bill-requests', $profile_url );
        } elseif ( $is_dashboard ) {
            $profile_url = erp_hr_url_single_employee( get_current_user_id() );
            $list_url    = add_query_arg( 'tab', 'bill-requests', $profile_url );
        } else {
            $list_url = admin_url( 'admin.php?page=erp-hr&section=payment-requests' );
        }

        wp_localize_script(
            'erp-app-helper-payment-request', 'erpPaymentRequest', [
				'ajaxurl'  => admin_url( 'admin-ajax.php' ),
				'nonce'    => wp_create_nonce( 'erp-payment-request-nonce' ),
				'listUrl'  => $list_url,
				'i18n'     => [
					'newTitle'          => __( 'New Bill Request', 'wp-erp-app-helper' ),
					'editTitle'         => __( 'Edit Bill Request', 'wp-erp-app-helper' ),
                    'selectEmployee'    => __( 'Please select an employee.', 'wp-erp-app-helper' ),
					'selectFiles'       => __( 'Select Files', 'wp-erp-app-helper' ),
					'attachFiles'       => __( 'Attach Files', 'wp-erp-app-helper' ),
					'fileTooLarge'      => __( 'File "{name}" exceeds the 10 MB size limit.', 'wp-erp-app-helper' ),
					'invalidType'       => __( 'File "{name}" is not a supported type (PDF, JPG, PNG only).', 'wp-erp-app-helper' ),
					'confirmApproval'      => __( 'Confirm Approval', 'wp-erp-app-helper' ),
					'paymentTypeRequired'  => __( 'Please select a payment type.', 'wp-erp-app-helper' ),
					'noteRequired'      => __( 'A rejection note is required.', 'wp-erp-app-helper' ),
					'attachmentRequired' => __( 'At least one attachment is required.', 'wp-erp-app-helper' ),
					'fieldRequired'     => __( '{field} is required.', 'wp-erp-app-helper' ),
					'submitting'        => __( 'Submitting...', 'wp-erp-app-helper' ),
					'submit'            => __( 'Submit Request', 'wp-erp-app-helper' ),
					'saveChanges'       => __( 'Save Changes', 'wp-erp-app-helper' ),
					'submitRejection'   => __( 'Reject Request', 'wp-erp-app-helper' ),
					'error'             => __( 'An error occurred. Please try again.', 'wp-erp-app-helper' ),
					'successTitle'      => __( 'Request Submitted!', 'wp-erp-app-helper' ),
					'successMessage'    => __( 'Your bill request has been submitted successfully.', 'wp-erp-app-helper' ),
					'successTitleEdit'  => __( 'Request Updated!', 'wp-erp-app-helper' ),
					'successMessageEdit' => __( 'Your bill request has been updated successfully.', 'wp-erp-app-helper' ),
				],
			]
        );
    }

    /**
     * AJAX: Submit a new payment request
     */
    public function ajax_submit_payment_request() {
        check_ajax_referer( 'erp-payment-request-nonce', 'nonce' );

        $is_hr_manager = current_user_can( 'erp_manage_hr_settings' ) || current_user_can( 'manage_options' );

        if ( ! current_user_can( 'erp_list_employee' ) && ! $is_hr_manager ) {
            wp_send_json_error( __( 'Permission denied.', 'wp-erp-app-helper' ) );
        }

        if ( ! $this->maybe_add_created_by_column() ) {
            wp_send_json_error( __( 'Failed to initialize request metadata. Please try again.', 'wp-erp-app-helper' ) );
        }

        $title              = isset( $_POST['title'] ) ? sanitize_text_field( wp_unslash( $_POST['title'] ) ) : '';
        $amount             = isset( $_POST['amount'] ) ? (float) $_POST['amount'] : 0;
        $description        = isset( $_POST['description'] ) ? sanitize_textarea_field( wp_unslash( $_POST['description'] ) ) : '';
        $purchase_date      = isset( $_POST['purchase_date'] ) ? sanitize_text_field( wp_unslash( $_POST['purchase_date'] ) ) : '';
        $expect_payment_by  = isset( $_POST['expect_payment_by'] ) ? sanitize_text_field( wp_unslash( $_POST['expect_payment_by'] ) ) : '';
        $attachment_ids     = isset( $_POST['attachment_ids'] ) ? array_map( 'absint', (array) $_POST['attachment_ids'] ) : [];

        if ( empty( $title ) || $amount <= 0 || empty( $description ) || empty( $attachment_ids ) ) {
            wp_send_json_error( __( 'Title, a positive amount, description, and at least one attachment are required.', 'wp-erp-app-helper' ) );
        }

        $validation_error = $this->validate_attachments( $attachment_ids, $is_hr_manager );
        if ( is_wp_error( $validation_error ) ) {
            wp_send_json_error( $validation_error->get_error_message() );
        }

        $employee_id = get_current_user_id();
        if ( $is_hr_manager ) {
            $employee_id = isset( $_POST['employee_id'] ) ? absint( $_POST['employee_id'] ) : 0;
            if ( ! $employee_id || ! get_userdata( $employee_id ) ) {
                wp_send_json_error( __( 'Please select a valid employee.', 'wp-erp-app-helper' ) );
            }
        }

        global $wpdb;
        $now = current_time( 'mysql' );

        $inserted = $wpdb->insert(
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

        if ( ! $inserted ) {
            wp_send_json_error( __( 'Failed to save request.', 'wp-erp-app-helper' ) );
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

        wp_send_json_success( [ 'id' => $request_id ] );
    }

    /**
     * AJAX: Update a pending payment request
     */
    public function ajax_update_payment_request() {
        check_ajax_referer( 'erp-payment-request-nonce', 'nonce' );

        $is_hr_manager = current_user_can( 'erp_manage_hr_settings' ) || current_user_can( 'manage_options' );

        if ( ! current_user_can( 'erp_list_employee' ) && ! $is_hr_manager ) {
            wp_send_json_error( __( 'Permission denied.', 'wp-erp-app-helper' ) );
        }

        if ( ! $this->maybe_add_created_by_column() ) {
            wp_send_json_error( __( 'Failed to initialize request metadata. Please try again.', 'wp-erp-app-helper' ) );
        }

        $request_id         = isset( $_POST['request_id'] ) ? absint( $_POST['request_id'] ) : 0;
        $title              = isset( $_POST['title'] ) ? sanitize_text_field( wp_unslash( $_POST['title'] ) ) : '';
        $amount             = isset( $_POST['amount'] ) ? (float) $_POST['amount'] : 0;
        $description        = isset( $_POST['description'] ) ? sanitize_textarea_field( wp_unslash( $_POST['description'] ) ) : '';
        $purchase_date      = isset( $_POST['purchase_date'] ) ? sanitize_text_field( wp_unslash( $_POST['purchase_date'] ) ) : '';
        $expect_payment_by  = isset( $_POST['expect_payment_by'] ) ? sanitize_text_field( wp_unslash( $_POST['expect_payment_by'] ) ) : '';
        $attachment_ids     = isset( $_POST['attachment_ids'] ) ? array_map( 'absint', (array) $_POST['attachment_ids'] ) : [];

        if ( ! $request_id ) {
            wp_send_json_error( __( 'Invalid request.', 'wp-erp-app-helper' ) );
        }
        if ( empty( $title ) || $amount <= 0 || empty( $description ) || empty( $attachment_ids ) ) {
            wp_send_json_error( __( 'Title, a positive amount, description, and at least one attachment are required.', 'wp-erp-app-helper' ) );
        }

        global $wpdb;

        $request = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}erp_payment_requests WHERE id = %d",
                $request_id
            )
        );

        if ( ! $request ) {
            wp_send_json_error( __( 'Request not found.', 'wp-erp-app-helper' ) );
        }
        $is_owner      = (int) $request->employee_id === get_current_user_id();
        $is_hr_creator = $is_hr_manager && ! empty( $request->created_by ) && (int) $request->created_by === get_current_user_id();

        if ( ! $is_owner && ! $is_hr_creator ) {
            wp_send_json_error( __( 'Permission denied.', 'wp-erp-app-helper' ) );
        }
        if ( 'pending' !== $request->status ) {
            wp_send_json_error( __( 'Only pending requests can be edited.', 'wp-erp-app-helper' ) );
        }

        // Validate new attachment set — allow attachments already owned by current user
        // (includes previously saved ones which keep their post_author)
        $validation_error = $this->validate_attachments( $attachment_ids, $is_hr_creator );
        if ( is_wp_error( $validation_error ) ) {
            wp_send_json_error( $validation_error->get_error_message() );
        }

        $wpdb->update(
            "{$wpdb->prefix}erp_payment_requests",
            [
                'title'             => $title,
                'amount'            => $amount,
                'description'       => $description,
                'purchase_date'     => $purchase_date,
                'expect_payment_by' => $expect_payment_by,
                'updated_at'        => current_time( 'mysql' ),
            ],
            [ 'id' => $request_id ],
            [ '%s', '%f', '%s', '%s', '%s', '%s' ],
            [ '%d' ]
        );

        // Replace attachment links
        $wpdb->delete( "{$wpdb->prefix}erp_payment_request_attachments", [ 'request_id' => $request_id ], [ '%d' ] );

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

        wp_send_json_success( [ 'id' => $request_id ] );
    }

    /**
     * AJAX: HR approves or rejects a payment request
     */
    public function ajax_review_payment_request() {
        check_ajax_referer( 'erp-payment-request-nonce', 'nonce' );

        if ( ! current_user_can( 'erp_manage_hr_settings' ) && ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( __( 'Permission denied.', 'wp-erp-app-helper' ) );
        }

        $request_id   = isset( $_POST['request_id'] ) ? absint( $_POST['request_id'] ) : 0;
        $action_type  = isset( $_POST['action_type'] ) ? sanitize_key( $_POST['action_type'] ) : '';
        $hr_note      = isset( $_POST['hr_note'] ) ? sanitize_textarea_field( wp_unslash( $_POST['hr_note'] ) ) : '';
        $payment_type = isset( $_POST['payment_type'] ) ? sanitize_key( $_POST['payment_type'] ) : '';

        if ( ! $request_id || ! in_array( $action_type, [ 'approve', 'reject' ], true ) ) {
            wp_send_json_error( __( 'Invalid request.', 'wp-erp-app-helper' ) );
        }

        if ( 'approve' === $action_type && ! in_array( $payment_type, [ 'cash', 'bank_transfer' ], true ) ) {
            wp_send_json_error( __( 'Please select a payment type.', 'wp-erp-app-helper' ) );
        }

        if ( 'reject' === $action_type && empty( $hr_note ) ) {
            wp_send_json_error( __( 'A rejection note is required.', 'wp-erp-app-helper' ) );
        }

        global $wpdb;
        $request = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}erp_payment_requests WHERE id = %d",
                $request_id
            )
        );

        if ( ! $request ) {
            wp_send_json_error( __( 'Request not found.', 'wp-erp-app-helper' ) );
        }

        if ( 'pending' !== $request->status ) {
            wp_send_json_error( __( 'Only pending requests can be reviewed.', 'wp-erp-app-helper' ) );
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
            [ 'id' => $request_id ],
            [ '%s', '%s', '%s', '%d', '%s', '%s' ],
            [ '%d' ]
        );

        $updated_request = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}erp_payment_requests WHERE id = %d",
                $request_id
            )
        );
        $this->send_notification( $updated_request, $new_status );

        wp_send_json_success( __( 'Request updated successfully.', 'wp-erp-app-helper' ) );
    }

    /**
     * Send email notification to employee on approve/reject
     */
    public function send_notification( $request, $status ) {
        $employee = get_userdata( $request->employee_id );
        if ( ! $employee ) {
            return;
        }

        $to = $employee->user_email;
        $subject = sprintf(
            /* translators: 1: request title, 2: status (approved/rejected) */
            __( 'Your payment request "%1$s" has been %2$s', 'wp-erp-app-helper' ),
            $request->title,
            $status
        );

        /* translators: 1: employee name, 2: request title, 3: amount, 4: status */
        $body = sprintf(
            __( "Dear %1\$s,\n\nYour payment request \"%2\$s\" for BDT %3\$s has been %4\$s.\n", 'wp-erp-app-helper' ),
            $employee->display_name,
            $request->title,
            number_format( $request->amount, 2 ),
            $status
        );

        if ( 'rejected' === $status && ! empty( $request->hr_note ) ) {
            /* translators: %s: rejection reason */
            $body .= "\n" . sprintf( __( 'Reason: %s', 'wp-erp-app-helper' ), $request->hr_note );
        }

        $body .= "\n\n" . get_bloginfo( 'name' );

        if ( function_exists( 'erp_mail' ) ) {
            erp_mail( $to, $subject, $body );
        } else {
            wp_mail( $to, $subject, $body );
        }
    }

    /**
     * Render reject modal template in admin footer
     */
    public function render_modal_template() {
        $page    = isset( $_GET['page'] ) ? sanitize_text_field( $_GET['page'] ) : ''; // phpcs:ignore
        $section = isset( $_GET['section'] ) ? sanitize_text_field( $_GET['section'] ) : ''; // phpcs:ignore
        $action  = isset( $_GET['action'] ) ? sanitize_text_field( $_GET['action'] ) : ''; // phpcs:ignore
        $tab     = isset( $_GET['tab'] ) ? sanitize_text_field( $_GET['tab'] ) : ''; // phpcs:ignore

        $is_hr_queue    = 'erp-hr' === $page && 'payment-requests' === $section;
        $is_profile_tab = 'erp-hr' === $page && 'view' === $action && 'bill-requests' === $tab;
        $is_dashboard   = 'erp-hr' === $page && in_array( $section, [ 'dashboard', '' ], true );

        if ( ! $is_hr_queue && ! $is_profile_tab && ! $is_dashboard ) {
            return;
        }

        welabs_wp_erp_app_helper()->get_template( 'payment-request-modal.php' );
        welabs_wp_erp_app_helper()->get_template( 'payment-request-form-modal.php' );
    }

    /**
     * Validate attachment IDs: ownership, MIME type, and file size
     *
     * @return true|\WP_Error
     */
    public function validate_attachments( array $attachment_ids, $allow_non_owner = false ) {
        $current_user_id = get_current_user_id();

        foreach ( $attachment_ids as $att_id ) {
            $post = get_post( $att_id );

            if ( ! $post || 'attachment' !== $post->post_type ) {
                /* translators: %d: attachment ID */
                return new \WP_Error( 'invalid_attachment', sprintf( __( 'Attachment %d not found.', 'wp-erp-app-helper' ), $att_id ) );
            }

            if ( ! $allow_non_owner && (int) $post->post_author !== $current_user_id ) {
                /* translators: %d: attachment ID */
                return new \WP_Error( 'attachment_ownership', sprintf( __( 'Attachment %d does not belong to you.', 'wp-erp-app-helper' ), $att_id ) );
            }

            $mime = get_post_mime_type( $att_id );
            if ( ! in_array( $mime, self::ALLOWED_MIME_TYPES, true ) ) {
                /* translators: 1: attachment ID, 2: MIME type */
                return new \WP_Error( 'invalid_mime', sprintf( __( 'Attachment %1$d has an unsupported file type (%2$s).', 'wp-erp-app-helper' ), $att_id, $mime ) );
            }

            $filepath = get_attached_file( $att_id );
            if ( $filepath && file_exists( $filepath ) && filesize( $filepath ) > self::MAX_FILE_SIZE ) {
                /* translators: %d: attachment ID */
                return new \WP_Error( 'file_too_large', sprintf( __( 'Attachment %d exceeds the 10 MB size limit.', 'wp-erp-app-helper' ), $att_id ) );
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
