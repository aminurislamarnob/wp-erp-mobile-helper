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
        add_filter( 'erp_leave_request_row_actions', [ $this, 'add_required_approval_action' ], 10, 2 );
        add_filter( 'erp_leave_request_employee_name_column', [ $this, 'display_required_approval_status' ], 10, 2 );
        add_filter( 'erp_hr_get_leave_requests', [ $this, 'include_approval_data' ], 10, 2 );
        
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_scripts' ] );
        add_action( 'admin_footer', [ $this, 'render_modal_template' ] );

        // AJAX handlers
        add_action( 'wp_ajax_erp_app_helper_get_team_leads', [ $this, 'get_team_leads' ] );
        add_action( 'wp_ajax_erp_app_helper_save_required_approval', [ $this, 'save_required_approval' ] );
    }

    /**
     * Add "Required Approval" row action
     *
     * @param array $actions
     * @param object $item
     * @return array
     */
    public function add_required_approval_action( $actions, $item ) {
        if ( $item->status == '2' && empty( $item->required_approver ) ) { // Only for pending and if not already set
            $actions['required_approval'] = sprintf(
                '<a href="#" class="erp-app-helper-required-approval" data-id="%d" data-name="%s">%s</a>',
                $item->id,
                esc_attr( $item->name ),
                __( 'Required Approval', 'wp-erp-app-helper' )
            );
        }
        return $actions;
    }

    /**
     * Display the required approval status badge
     *
     * @param string $content
     * @param int $request_id
     * @return string
     */
    public function display_required_approval_status( $content, $request_id ) {
        global $wpdb;
        $request = $wpdb->get_row( "SELECT required_approver, approval_status FROM {$wpdb->prefix}erp_hr_leave_requests WHERE id = $request_id" );

        if ( ! empty( $request->required_approver ) ) {
            $approver = get_user_by( 'id', $request->required_approver );
            $status = $request->approval_status ? $request->approval_status : 'Pending';
            $class = 'status-' . strtolower( $status );

            $content .= sprintf(
                '<div class="erp-app-helper-approval-badge %s" title="%s">%s: %s (%s)</div>',
                $class,
                __( 'Approval required before final approval', 'wp-erp-app-helper' ),
                __( 'Required Approval', 'wp-erp-app-helper' ),
                $approver->display_name,
                $status
            );
        }

        return $content;
    }

    /**
     * Ensure custom columns are included when fetching leave requests
     *
     * @param array $results
     * @param array $args
     * @return array
     */
    public function include_approval_data( $results, $args ) {
        // Since we filtered the DB table, WP-ERP's query *might* already pick them up if it uses SELECT *
        // but it's safer to ensure they are there if needed for other logic.
        return $results;
    }

    /**
     * Enqueue scripts and styles
     */
    public function enqueue_scripts( $hook ) {
        if ( ! isset( $_GET['page'] ) || 'erp-hr' !== $_GET['page'] ) {
            return;
        }

        wp_enqueue_style( 'erp-app-helper-admin', WP_ERP_APP_HELPER_PLUGIN_ASSET . '/admin/css/admin.css', [], WP_ERP_APP_HELPER_PLUGIN_VERSION );
        wp_enqueue_script( 'erp-app-helper-leave-approval', WP_ERP_APP_HELPER_PLUGIN_ASSET . '/admin/js/leave-approval.js', [ 'jquery', 'erp-script' ], WP_ERP_APP_HELPER_PLUGIN_VERSION, true );

        wp_localize_script( 'erp-app-helper-leave-approval', 'erpAppHelper', [
            'ajaxurl' => admin_url( 'admin-ajax.php' ),
            'nonce'   => wp_create_nonce( 'erp-app-helper-nonce' ),
            'i18n'    => [
                'selectClead' => __( 'Select Team Lead or Higher', 'wp-erp-app-helper' ),
                'save'        => __( 'Save', 'wp-erp-app-helper' ),
                'cancel'      => __( 'Cancel', 'wp-erp-app-helper' ),
                'loading'     => __( 'Loading...', 'wp-erp-app-helper' ),
                'success'     => __( 'Required approval saved successfully.', 'wp-erp-app-helper' ),
            ]
        ] );
    }

    /**
     * Render the modal template
     */
    public function render_modal_template() {
        if ( ! isset( $_GET['page'] ) || 'erp-hr' !== $_GET['page'] ) {
            return;
        }

        welabs_wp_erp_app_helper()->get_template( 'leave-approval-modal.php' );
    }

    /**
     * Get employees with Team Lead or higher capability
     */
    public function get_team_leads() {
        check_ajax_referer( 'erp-app-helper-nonce', 'nonce' );

        $users = get_users( [
            'role__in' => [ 'erp_team_lead', 'erp_hr_manager', 'administrator' ],
            'fields'   => [ 'ID', 'display_name' ],
        ] );

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
        $message = sprintf( __( 'Waiting for additional approval from %s', 'wp-erp-app-helper' ), $approver->display_name );

        $wpdb->insert( "{$wpdb->prefix}erp_hr_leave_approval_status", [
            'leave_request_id'   => $request_id,
            'approval_status_id' => 2, // Keep it pending
            'message'            => $message,
            'approved_by'        => get_current_user_id(),
            'created_at'         => time(),
        ] );

        wp_send_json_success();
    }
}
