<?php

namespace WeLabs\WpErpAppHelper;

class Standup {

    public function __construct() {
        add_action( 'admin_menu', [ $this, 'admin_menu' ] );
        add_filter( 'map_meta_cap', [ $this, 'map_standup_cap' ], 10, 4 );
    }

    /**
     * Map the custom capability strictly for the required roles.
     */
    public function map_standup_cap( $caps, $cap, $user_id, $args ) {
        if ( 'erp_manage_standup' === $cap ) {
            $user = get_userdata( $user_id );
            if ( ! $user ) {
                return [ 'do_not_allow' ];
            }

            // Administrators and HR managers (mirrors ERP's own cap-mapping pattern).
            if ( user_can( $user_id, 'manage_options' ) || user_can( $user_id, erp_hr_get_manager_role() ) ) {
                return [ 'exist' ];
            }

            // Team Lead role gets explicit access.
            if ( in_array( 'erp_team_lead', $user->roles, true ) ) {
                return [ 'exist' ];
            }

            return [ 'do_not_allow' ];
        }

        return $caps;
    }

    /**
     * Register the menu in WP ERP HR module
     */
    public function admin_menu() {
        if ( function_exists( 'erp_add_menu' ) ) {
            erp_add_menu(
                'hr', [
					'title'      => __( 'Standup Tracker', 'wp-erp-app-helper' ),
					'slug'       => 'standup-tracker',
					'capability' => 'erp_manage_standup',
					'callback'   => [ $this, 'render_page' ],
					'position'   => 90,
				]
            );
        }
    }

    /**
     * Render the Standup Tracker page
     */
    public function render_page() {
        if ( ! current_user_can( 'erp_manage_standup' ) ) {
            wp_die( __( 'You do not have sufficient permissions to access this page.', 'wp-erp-app-helper' ) );
        }

        welabs_wp_erp_app_helper()->get_template( 'standup-tracker.php' );
    }
}
