<?php
namespace WeLabs\WpErpAppHelper;

use WP_REST_Server;
use WP_REST_Request;
use WP_REST_Response;
use WP_Error;

/**
 * BiometricAuthController — biometric (fingerprint) login endpoints under erp-app/v1
 *
 * Flow:
 *  1. App calls POST /biometric/register (with valid Bearer token) after normal login.
 *     Server issues a biometric token; app stores it in the device secure keychain.
 *  2. On subsequent opens, the device OS verifies the fingerprint and releases the
 *     stored token. App calls POST /biometric/login with that token.
 *     Server returns a fresh auth session identical to the /login response.
 *  3. App calls DELETE /biometric/revoke to invalidate the biometric token
 *     (e.g. on logout or when the user disables biometric login in settings).
 */
class BiometricAuthController {

    const REST_NAMESPACE      = 'erp-app/v1';
    const BIOMETRIC_META_KEY  = 'erp_app_biometric_token';

    public function register_routes() {
        register_rest_route(
            self::REST_NAMESPACE, '/biometric/register', [
                [
                    'methods'             => WP_REST_Server::CREATABLE,
                    'callback'            => [ $this, 'register' ],
                    'permission_callback' => 'is_user_logged_in',
                ],
            ]
        );

        register_rest_route(
            self::REST_NAMESPACE, '/biometric/login', [
                [
                    'methods'             => WP_REST_Server::CREATABLE,
                    'callback'            => [ $this, 'login' ],
                    'permission_callback' => '__return_true',
                ],
            ]
        );

        register_rest_route(
            self::REST_NAMESPACE, '/biometric/revoke', [
                [
                    'methods'             => WP_REST_Server::DELETABLE,
                    'callback'            => [ $this, 'revoke' ],
                    'permission_callback' => 'is_user_logged_in',
                ],
            ]
        );
    }

    /**
     * POST /biometric/register
     *
     * Requires a valid Bearer token. Issues a biometric token for the current user.
     * Calling this again replaces any previously issued biometric token.
     */
    public function register( WP_REST_Request $request ) {
        $user_id        = get_current_user_id();
        $biometric_token = wp_generate_password( 64, false, false );
        $token_hash      = hash( 'sha256', $biometric_token );

        update_user_meta( $user_id, self::BIOMETRIC_META_KEY, $token_hash );

        return rest_ensure_response( [
            'success'         => true,
            'biometric_token' => $biometric_token,
        ] );
    }

    /**
     * POST /biometric/login
     *
     * Accepts a biometric token (stored in device keychain after /biometric/register).
     * Returns a fresh auth session identical to the /login response shape.
     */
    public function login( WP_REST_Request $request ) {
        $biometric_token = $request->get_param( 'biometric_token' );

        if ( empty( $biometric_token ) ) {
            return new WP_Error( 'missing_token', __( 'Biometric token is required.', 'wp-erp-app-helper' ), [ 'status' => 400 ] );
        }

        $token_hash = hash( 'sha256', sanitize_text_field( $biometric_token ) );

        global $wpdb;

        $user_id = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT user_id FROM {$wpdb->usermeta} WHERE meta_key = %s AND meta_value = %s LIMIT 1",
                self::BIOMETRIC_META_KEY,
                $token_hash
            )
        );

        if ( ! $user_id ) {
            return new WP_Error( 'invalid_biometric_token', __( 'Invalid or expired biometric token.', 'wp-erp-app-helper' ), [ 'status' => 401 ] );
        }

        $user = get_userdata( (int) $user_id );

        if ( ! $user ) {
            return new WP_Error( 'invalid_user', __( 'User not found.', 'wp-erp-app-helper' ), [ 'status' => 401 ] );
        }

        // Issue a fresh auth token — same as the normal login flow.
        $auth_token      = wp_generate_password( 64, false, false );
        $auth_token_hash = hash( 'sha256', $auth_token );

        update_user_meta( $user->ID, Auth::TOKEN_META_KEY, $auth_token_hash );

        $avatar_urls = [];
        foreach ( [ 24, 48, 96 ] as $size ) {
            $avatar_urls[ $size ] = get_avatar_url( $user->ID, [ 'size' => $size ] );
        }

        return rest_ensure_response( [
            'success' => true,
            'token'   => $auth_token,
            'user'    => [
                'id'          => $user->ID,
                'name'        => $user->display_name,
                'slug'        => $user->user_nicename,
                'roles'       => $user->roles ? array_values( $user->roles ) : [],
                'avatar_urls' => $avatar_urls,
            ],
        ] );
    }

    /**
     * DELETE /biometric/revoke
     *
     * Removes the biometric token for the current user.
     */
    public function revoke( WP_REST_Request $request ) {
        delete_user_meta( get_current_user_id(), self::BIOMETRIC_META_KEY );

        return rest_ensure_response( [ 'success' => true ] );
    }
}
