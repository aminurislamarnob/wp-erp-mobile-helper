<?php

namespace WeLabs\WpErpAppHelper;

/**
 * Auth class
 *
 * Provides token-based REST API authentication for the WP-ERP app.
 * Mirrors the logic from erp-mobile-auth.php, adapted to the plugin's OOP structure.
 *
 * @since 0.0.1
 */
class Auth {

	/**
	 * REST API namespace.
	 *
	 * @var string
	 */
	const REST_NAMESPACE = 'erp-app/v1';

	/**
	 * User meta key used to store the hashed auth token.
	 *
	 * @var string
	 */
	const TOKEN_META_KEY = 'erp_app_token';

	/**
	 * Constructor — registers all hooks.
	 */
	public function __construct() {
		add_filter( 'determine_current_user', [ $this, 'authenticate_via_token' ], 20 );
		add_filter( 'rest_authentication_errors', [ $this, 'bypass_cookie_nonce_check' ], 99 );
	}

	/**
	 * Authenticate REST requests via Bearer token.
	 *
	 * Runs on the 'determine_current_user' filter so WordPress
	 * treats the request as if the user is logged in — no cookies needed.
	 *
	 * @param int|false $user_id Current resolved user ID (0 if none).
	 *
	 * @return int|false
	 */
	public function authenticate_via_token( $user_id ) {
		// Don't override if already authenticated.
		if ( $user_id ) {
			return $user_id;
		}

		// Only act on REST API requests.
		if ( ! defined( 'REST_REQUEST' ) || ! REST_REQUEST ) {
			return $user_id;
		}

		$token = $this->extract_bearer_token();
		if ( empty( $token ) ) {
			return $user_id;
		}

		$token_hash = hash( 'sha256', $token );

		global $wpdb;

		$found_user_id = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT user_id FROM {$wpdb->usermeta} WHERE meta_key = %s AND meta_value = %s LIMIT 1",
				self::TOKEN_META_KEY,
				$token_hash
			)
		);

		if ( $found_user_id ) {
			return (int) $found_user_id;
		}

		return $user_id;
	}

	/**
	 * Bypass REST cookie/nonce check for token-authenticated requests.
	 *
	 * WordPress normally requires a valid nonce via X-WP-Nonce for cookie auth.
	 * With token auth the user is already set via determine_current_user,
	 * so we skip the nonce requirement.
	 *
	 * @param WP_Error|null|true $errors Existing auth errors.
	 *
	 * @return WP_Error|null|true
	 */
	public function bypass_cookie_nonce_check( $errors ) {
		// If there's already an explicit error from another auth handler, respect it.
		if ( is_wp_error( $errors ) ) {
			return $errors;
		}

		$auth_header = $this->get_authorization_header();
		if ( preg_match( '/^Bearer\s+.+$/i', $auth_header ) ) {
			return true; // Authenticated — bypass cookie nonce check.
		}

		return $errors;
	}

	/**
	 * Register REST routes.
	 *
	 * POST /wp-json/erp-app/v1/login
	 * POST /wp-json/erp-app/v1/logout
	 *
	 * @return void
	 */
	public function register_routes() {
		register_rest_route(
			self::REST_NAMESPACE,
			'/login',
			[
				[
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => [ $this, 'login' ],
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
			]
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/logout',
			[
				[
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => [ $this, 'logout' ],
					'permission_callback' => function () {
						return is_user_logged_in();
					},
				],
			]
		);
	}

	/**
	 * Authenticate user and return a persistent auth token.
	 *
	 * @param \WP_REST_Request $request The REST request object.
	 *
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function login( \WP_REST_Request $request ) {
		$username = $request->get_param( 'username' );
		$password = $request->get_param( 'password' );

		$user = wp_authenticate( $username, $password );

		if ( is_wp_error( $user ) ) {
			return new \WP_Error(
				'invalid_credentials',
				__( 'Invalid username or password.', 'wp-erp-app-helper' ),
				[ 'status' => 401 ]
			);
		}

		// Generate a random token.
		$token      = wp_generate_password( 64, false, false );
		$token_hash = hash( 'sha256', $token );

		// Store hashed token in user meta (one token per user — replaces previous).
		update_user_meta( $user->ID, self::TOKEN_META_KEY, $token_hash );

		// Set current user so any downstream code in this request works.
		wp_set_current_user( $user->ID );

		// Build user data.
		$user_data = [
			'id'    => $user->ID,
			'name'  => $user->display_name,
			'slug'  => $user->user_nicename,
			'roles' => $user->roles ? array_values( $user->roles ) : [],
		];

		// Avatar URLs.
		$avatar_urls = [];
		foreach ( [ 24, 48, 96 ] as $size ) {
			$avatar_urls[ $size ] = get_avatar_url( $user->ID, [ 'size' => $size ] );
		}
		$user_data['avatar_urls'] = $avatar_urls;

		return rest_ensure_response(
			[
				'success' => true,
				'token'   => $token,
				'user'    => $user_data,
			]
		);
	}

	/**
	 * Revoke the current app auth token.
	 *
	 * @param \WP_REST_Request $request The REST request object.
	 *
	 * @return \WP_REST_Response
	 */
	public function logout( \WP_REST_Request $request ) {
		$user_id = get_current_user_id();
		delete_user_meta( $user_id, self::TOKEN_META_KEY );

		return rest_ensure_response( [ 'success' => true ] );
	}

	// -------------------------------------------------------------------------
	// Private helpers
	// -------------------------------------------------------------------------

	/**
	 * Extract the raw Bearer token from the Authorization header.
	 *
	 * @return string Token string, or empty string if not found / not Bearer.
	 */
	private function extract_bearer_token() {
		$auth_header = $this->get_authorization_header();

		if ( ! preg_match( '/^Bearer\s+(.+)$/i', $auth_header, $matches ) ) {
			return '';
		}

		return sanitize_text_field( $matches[1] );
	}

	/**
	 * Retrieve the raw Authorization header value across server environments.
	 *
	 * Tries HTTP_AUTHORIZATION, then REDIRECT_HTTP_AUTHORIZATION (FastCGI),
	 * then falls back to getallheaders().
	 *
	 * @return string
	 */
	private function get_authorization_header() {
		if ( isset( $_SERVER['HTTP_AUTHORIZATION'] ) ) {
			return $_SERVER['HTTP_AUTHORIZATION']; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		}

		if ( isset( $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ) ) {
			return $_SERVER['REDIRECT_HTTP_AUTHORIZATION']; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		}

		if ( function_exists( 'getallheaders' ) ) {
			$headers = getallheaders();
			return $headers['Authorization'] ?? $headers['authorization'] ?? '';
		}

		return '';
	}
}
