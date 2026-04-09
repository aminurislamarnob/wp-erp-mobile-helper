<?php
/**
 * Plugin Name: ERP Mobile Auth
 * Description: Provides token-based REST API authentication for the WP-ERP mobile app.
 * Version: 2.0.0
 * Author: weLabs
 * Requires at least: 5.8
 * Requires PHP: 7.4
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Authenticate REST requests via Bearer token.
 * Runs on the 'determine_current_user' filter so WordPress
 * treats the request as if the user is logged in — no cookies needed.
 */
add_filter( 'determine_current_user', function ( $user_id ) {
    // Don't override if already authenticated
    if ( $user_id ) {
        return $user_id;
    }

    // Only act on REST API requests
    if ( ! defined( 'REST_REQUEST' ) || ! REST_REQUEST ) {
        return $user_id;
    }

    $auth_header = '';
    if ( isset( $_SERVER['HTTP_AUTHORIZATION'] ) ) {
        $auth_header = $_SERVER['HTTP_AUTHORIZATION'];
    } elseif ( isset( $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ) ) {
        $auth_header = $_SERVER['REDIRECT_HTTP_AUTHORIZATION'];
    } elseif ( function_exists( 'getallheaders' ) ) {
        $headers = getallheaders();
        $auth_header = $headers['Authorization'] ?? $headers['authorization'] ?? '';
    }

    if ( ! preg_match( '/^Bearer\s+(.+)$/i', $auth_header, $matches ) ) {
        return $user_id;
    }

    $token = sanitize_text_field( $matches[1] );
    if ( empty( $token ) ) {
        return $user_id;
    }

    // Look up token hash in user meta
    $token_hash = hash( 'sha256', $token );

    global $wpdb;
    $found_user_id = $wpdb->get_var( $wpdb->prepare(
        "SELECT user_id FROM {$wpdb->usermeta} WHERE meta_key = 'erp_mobile_token' AND meta_value = %s LIMIT 1",
        $token_hash
    ) );

    if ( $found_user_id ) {
        return (int) $found_user_id;
    }

    return $user_id;
}, 20 );

/**
 * Bypass REST cookie/nonce check for token-authenticated requests.
 * WordPress normally requires a valid nonce via X-WP-Nonce for cookie auth.
 * With token auth the user is already set via determine_current_user,
 * so we skip the nonce requirement.
 */
add_filter( 'rest_authentication_errors', function ( $errors ) {
    // If there's already an explicit error from another auth handler, respect it
    if ( is_wp_error( $errors ) ) {
        return $errors;
    }

    // If a Bearer token is present, clear the cookie nonce error
    $auth_header = '';
    if ( isset( $_SERVER['HTTP_AUTHORIZATION'] ) ) {
        $auth_header = $_SERVER['HTTP_AUTHORIZATION'];
    } elseif ( isset( $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ) ) {
        $auth_header = $_SERVER['REDIRECT_HTTP_AUTHORIZATION'];
    } elseif ( function_exists( 'getallheaders' ) ) {
        $headers = getallheaders();
        $auth_header = $headers['Authorization'] ?? $headers['authorization'] ?? '';
    }

    if ( preg_match( '/^Bearer\s+.+$/i', $auth_header ) ) {
        return true; // Authenticated — bypass cookie nonce check
    }

    return $errors;
}, 99 );

/**
 * Register REST routes.
 */
add_action( 'rest_api_init', function () {
    // POST /wp-json/erp-mobile/v1/login
    register_rest_route( 'erp-mobile/v1', '/login', [
        [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => 'erp_mobile_login',
            'permission_callback' => '__return_true',
            'args'                => [
                'username' => [
                    'required'          => true,
                    'type'              => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                ],
                'password' => [
                    'required' => true,
                    'type'     => 'string',
                ],
            ],
        ],
    ] );

    // POST /wp-json/erp-mobile/v1/logout
    register_rest_route( 'erp-mobile/v1', '/logout', [
        [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => 'erp_mobile_logout',
            'permission_callback' => function () {
                return is_user_logged_in();
            },
        ],
    ] );
} );

/**
 * Authenticate user and return a persistent auth token.
 */
function erp_mobile_login( WP_REST_Request $request ) {
    $username = $request->get_param( 'username' );
    $password = $request->get_param( 'password' );

    $user = wp_authenticate( $username, $password );

    if ( is_wp_error( $user ) ) {
        return new WP_Error(
            'invalid_credentials',
            __( 'Invalid username or password.', 'erp-mobile-auth' ),
            [ 'status' => 401 ]
        );
    }

    // Generate a random token
    $token = wp_generate_password( 64, false, false );

    // Store hashed token in user meta (one token per user — replaces previous)
    $token_hash = hash( 'sha256', $token );
    update_user_meta( $user->ID, 'erp_mobile_token', $token_hash );

    // Set current user so any downstream code in this request works
    wp_set_current_user( $user->ID );

    // Build user data
    $user_data = [
        'id'    => $user->ID,
        'name'  => $user->display_name,
        'slug'  => $user->user_nicename,
        'roles' => $user->roles ? array_values( $user->roles ) : [],
    ];

    // Avatar URLs
    $avatar_urls = [];
    foreach ( [ 24, 48, 96 ] as $size ) {
        $avatar_urls[ $size ] = get_avatar_url( $user->ID, [ 'size' => $size ] );
    }
    $user_data['avatar_urls'] = $avatar_urls;

    return rest_ensure_response( [
        'success' => true,
        'token'   => $token,
        'user'    => $user_data,
    ] );
}

/**
 * Revoke the current mobile auth token.
 */
function erp_mobile_logout( WP_REST_Request $request ) {
    $user_id = get_current_user_id();
    delete_user_meta( $user_id, 'erp_mobile_token' );

    return rest_ensure_response( [
        'success' => true,
    ] );
}
