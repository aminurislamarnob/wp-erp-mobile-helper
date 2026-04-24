<?php

namespace WeLabs\WpErpAppHelper;

class StandupWidget {

    private $is_script_enqueued = false;

    public function __construct() {
        add_action( 'erp_hr_dashboard_widgets_right', [ $this, 'register_widget' ], 11 );
        add_action( 'wp_ajax_erp_app_helper_standup_widget_data', [ $this, 'ajax_widget_data' ] );
    }

    public function register_widget() {
        erp_admin_dash_metabox(
            __( '<i class="fa fa-pie-chart"></i> Standup Progress', 'wp-erp-app-helper' ),
            [ $this, 'render_widget' ]
        );
    }

    public function render_widget() {
        $this->enqueue_scripts();
        welabs_wp_erp_app_helper()->get_template( 'standup-widget.php' );
    }

    private function enqueue_scripts() {
        if ( $this->is_script_enqueued ) {
            return;
        }

        wp_enqueue_style(
            'erp-app-helper-admin',
            WP_ERP_APP_HELPER_PLUGIN_ASSET . '/admin/css/admin.css',
            [],
            WP_ERP_APP_HELPER_PLUGIN_VERSION
        );

        wp_enqueue_script(
            'erp-app-helper-standup-widget',
            WP_ERP_APP_HELPER_PLUGIN_ADMIN_ASSET . '/js/standup-widget.js',
            [ 'jquery', 'erp-flotchart', 'erp-flotchart-pie' ],
            WP_ERP_APP_HELPER_PLUGIN_VERSION,
            true
        );

        wp_localize_script(
            'erp-app-helper-standup-widget',
            'erpStandupWidget',
            [
                'ajaxurl' => admin_url( 'admin-ajax.php' ),
                'nonce'   => wp_create_nonce( 'erp_standup_widget_nonce' ),
                'i18n'    => [
                    'present'   => __( 'Present', 'wp-erp-app-helper' ),
                    'absent'    => __( 'Absent', 'wp-erp-app-helper' ),
                    'leave'     => __( 'Leave', 'wp-erp-app-helper' ),
                    'thisMonth' => __( 'This Month', 'wp-erp-app-helper' ),
                    'lastMonth' => __( 'Last Month', 'wp-erp-app-helper' ),
                    'noData'    => __( 'No standup records found.', 'wp-erp-app-helper' ),
                    'loading'   => __( 'Loading…', 'wp-erp-app-helper' ),
                ],
            ]
        );

        $this->is_script_enqueued = true;
    }

    public function ajax_widget_data() {
        check_ajax_referer( 'erp_standup_widget_nonce', 'nonce' );

        $period  = isset( $_POST['period'] ) ? sanitize_text_field( wp_unslash( $_POST['period'] ) ) : 'this_month'; // phpcs:ignore WordPress.Security.NonceVerification.Missing
        $user_id = get_current_user_id();
        $today   = gmdate( 'Y-m-d' );

        if ( 'last_month' === $period ) {
            $month = gmdate( 'Y-m', strtotime( 'first day of last month' ) );
        } else {
            $month = gmdate( 'Y-m' );
        }

        $from = $month . '-01';
        $to   = gmdate( 'Y-m-t', strtotime( $from ) );

        if ( $to > $today ) {
            $to = $today;
        }

        global $wpdb;

        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT status, COUNT(*) AS cnt
                 FROM {$wpdb->prefix}erp_standup_tracker
                 WHERE employee_id = %d
                   AND standup_date BETWEEN %s AND %s
                 GROUP BY status",
                $user_id,
                $from,
                $to
            ),
            ARRAY_A
        );

        $data = [
            'present' => 0,
            'absent'  => 0,
            'leave'   => 0,
        ];

        foreach ( $rows as $row ) {
            if ( isset( $data[ $row['status'] ] ) ) {
                $data[ $row['status'] ] = (int) $row['cnt'];
            }
        }

        wp_send_json_success( $data );
    }
}
