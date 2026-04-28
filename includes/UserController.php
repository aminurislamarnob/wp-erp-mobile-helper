<?php
namespace WeLabs\WpErpAppHelper;

use WP_REST_Server;
use WP_REST_Request;
use WP_Error;

/**
 * UserController — user account REST endpoints under erp-app/v1
 */
class UserController {

    protected $namespace = 'erp-app/v1';

    public function register_routes() {
        register_rest_route(
            $this->namespace, '/user/change-password', [
                [
                    'methods'             => WP_REST_Server::CREATABLE,
                    'callback'            => [ $this, 'change_password' ],
                    'permission_callback' => 'is_user_logged_in',
                ],
            ]
        );
    }

    /**
     * POST /user/change-password
     */
    public function change_password( WP_REST_Request $request ) {
        $current_password     = $request->get_param( 'current_password' );
        $new_password         = $request->get_param( 'new_password' );
        $confirm_new_password = $request->get_param( 'confirm_new_password' );

        if ( empty( $current_password ) || empty( $new_password ) || empty( $confirm_new_password ) ) {
            return new WP_Error( 'missing_fields', __( 'All three password fields are required.', 'wp-erp-app-helper' ), [ 'status' => 400 ] );
        }

        if ( $new_password !== $confirm_new_password ) {
            return new WP_Error( 'password_mismatch', __( 'New password and confirmation do not match.', 'wp-erp-app-helper' ), [ 'status' => 400 ] );
        }

        $user = wp_get_current_user();

        if ( ! wp_check_password( $current_password, $user->user_pass, $user->ID ) ) {
            return new WP_Error( 'wrong_password', __( 'Current password is incorrect.', 'wp-erp-app-helper' ), [ 'status' => 403 ] );
        }

        wp_set_password( $new_password, $user->ID );

        return rest_ensure_response( [
            'success' => true,
            'message' => __( 'Password updated successfully.', 'wp-erp-app-helper' ),
        ] );
    }
}
