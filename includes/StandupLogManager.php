<?php
namespace WeLabs\WpErpAppHelper;

/**
 * StandupLogManager — employee-facing read-only standup log profile tab
 */
class StandupLogManager {

    public function __construct() {
        add_filter( 'erp_hr_employee_single_tabs', [ $this, 'add_profile_tab' ], 10, 2 );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_scripts' ] );
        add_action( 'wp_ajax_erp_app_helper_standup_log', [ $this, 'ajax_standup_log' ] );
    }

    /**
     * Inject "Standup Log" tab into the employee profile navigation.
     */
    public function add_profile_tab( $tabs, $employee ) {
        $is_own_profile = absint( $employee->get_user_id() ) === get_current_user_id();
        $is_hr_manager  = current_user_can( 'erp_manage_hr_settings' );

        if ( ! $is_own_profile && ! $is_hr_manager ) {
            return $tabs;
        }

        $tabs['standup-log'] = [
            'title'    => __( 'Standup Log', 'wp-erp-app-helper' ),
            'callback' => [ $this, 'render_standup_log_tab' ],
        ];

        return $tabs;
    }

    /**
     * Render the standup log tab for the given employee (initial server-side render).
     */
    public function render_standup_log_tab( $employee ) {
        global $wpdb;

        $employee_id   = absint( $employee->get_user_id() );
        $current_month = gmdate( 'Y-m' );

        $data = $this->get_month_data( $employee_id, $current_month );

        welabs_wp_erp_app_helper()->get_template(
            'standup-log.php',
            [
                'employee_id'   => $employee_id,
                'current_month' => $current_month,
                'entries'       => $data['entries'],
                'summary'       => $data['summary'],
            ]
        );
    }

    /**
     * Enqueue standup-log JS on the relevant profile tab page.
     */
    public function enqueue_scripts() {
        $page   = isset( $_GET['page'] ) ? sanitize_text_field( $_GET['page'] ) : ''; // phpcs:ignore
        $action = isset( $_GET['action'] ) ? sanitize_text_field( $_GET['action'] ) : ''; // phpcs:ignore
        $tab    = isset( $_GET['tab'] ) ? sanitize_text_field( $_GET['tab'] ) : ''; // phpcs:ignore
        $id     = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0; // phpcs:ignore

        if ( 'erp-hr' !== $page || 'view' !== $action || 'standup-log' !== $tab ) {
            return;
        }

        wp_enqueue_style( 'erp-app-helper-admin', WP_ERP_APP_HELPER_PLUGIN_ASSET . '/admin/css/admin.css', [], WP_ERP_APP_HELPER_PLUGIN_VERSION );

        wp_enqueue_script(
            'erp-app-helper-standup-log',
            WP_ERP_APP_HELPER_PLUGIN_ADMIN_ASSET . '/js/standup-log.js',
            [ 'jquery' ],
            WP_ERP_APP_HELPER_PLUGIN_VERSION,
            true
        );

        wp_localize_script(
            'erp-app-helper-standup-log',
            'erpStandupLog',
            [
                'ajaxurl'    => admin_url( 'admin-ajax.php' ),
                'nonce'      => wp_create_nonce( 'erp_standup_log_nonce' ),
                'employeeId' => $id,
                'i18n'       => [
                    'loading' => __( 'Loading…', 'wp-erp-app-helper' ),
                    'error'   => __( 'Failed to load records. Please try again.', 'wp-erp-app-helper' ),
                ],
            ]
        );
    }

    /**
     * AJAX handler: returns the standup-log-partial HTML for a given employee + month.
     */
    public function ajax_standup_log() {
        check_ajax_referer( 'erp_standup_log_nonce', 'nonce' );

        $employee_id = isset( $_POST['employee_id'] ) ? absint( $_POST['employee_id'] ) : 0; // phpcs:ignore
        $month       = isset( $_POST['month'] ) ? sanitize_text_field( $_POST['month'] ) : ''; // phpcs:ignore

        if ( ! $employee_id || ! preg_match( '/^\d{4}-\d{2}$/', $month ) ) {
            wp_send_json_error( [ 'message' => __( 'Invalid request.', 'wp-erp-app-helper' ) ] );
        }

        // Authorization: viewer must be the employee or an HR manager.
        $is_own     = absint( get_current_user_id() ) === $employee_id;
        $is_hr      = current_user_can( 'erp_manage_hr_settings' );

        if ( ! $is_own && ! $is_hr ) {
            wp_send_json_error( [ 'message' => __( 'You do not have permission to view this data.', 'wp-erp-app-helper' ) ] );
        }

        // Reject future months.
        if ( $month > gmdate( 'Y-m' ) ) {
            wp_send_json_error( [ 'message' => __( 'Cannot retrieve standup data for future months.', 'wp-erp-app-helper' ) ] );
        }

        $data = $this->get_month_data( $employee_id, $month );

        ob_start();
        welabs_wp_erp_app_helper()->get_template(
            'standup-log-partial.php',
            [
                'entries' => $data['entries'],
                'summary' => $data['summary'],
            ]
        );
        $html = ob_get_clean();

        wp_send_json_success( [ 'html' => $html ] );
    }

    /**
     * Query standup records and summary counts for an employee in a given month (YYYY-MM).
     *
     * @return array{ entries: array, summary: array{ present: int, absent: int, leave: int } }
     */
    private function get_month_data( $employee_id, $month ) {
        global $wpdb;

        $entries = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT standup_date, status
                 FROM {$wpdb->prefix}erp_standup_tracker
                 WHERE employee_id = %d AND standup_date LIKE %s
                 ORDER BY standup_date DESC",
                $employee_id,
                $month . '%'
            )
        );

        $summary = [
			'present' => 0,
			'absent' => 0,
			'leave' => 0,
		];
        foreach ( $entries as $entry ) {
            if ( isset( $summary[ $entry->status ] ) ) {
                ++$summary[ $entry->status ];
            }
        }

        return compact( 'entries', 'summary' );
    }
}
