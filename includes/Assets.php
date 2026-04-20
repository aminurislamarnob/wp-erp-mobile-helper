<?php

namespace WeLabs\WpErpAppHelper;

class Assets {
	/**
	 * The constructor.
	 */
	public function __construct() {
		add_action( 'init', array( $this, 'register_all_scripts' ), 10 );

		if ( is_admin() ) {
			add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_scripts' ), 10 );
		} else {
			add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_front_scripts' ) );
		}
	}

	/**
	 * Register all Dokan scripts and styles.
	 *
	 * @return void
	 */
	public function register_all_scripts() {
		$this->register_styles();
		$this->register_scripts();
	}

	/**
	 * Register scripts.
	 *
	 * @param array $scripts
	 *
	 * @return void
	 */
	public function register_scripts() {
		$admin_script    = WP_ERP_APP_HELPER_PLUGIN_ADMIN_ASSET . '/js/script.js';
		$frontend_script = WP_ERP_APP_HELPER_PLUGIN_PUBLIC_ASSET . '/js/script.js';

		wp_register_script( 'wp_erp_app_helper_admin_script', $admin_script, array(), WP_ERP_APP_HELPER_PLUGIN_VERSION, true );
		wp_register_script( 'wp_erp_app_helper_script', $frontend_script, array(), WP_ERP_APP_HELPER_PLUGIN_VERSION, true );
		wp_register_script( 'wp_erp_app_helper_standup_tracker_script', WP_ERP_APP_HELPER_PLUGIN_ADMIN_ASSET . '/js/standup-tracker.js', array(), WP_ERP_APP_HELPER_PLUGIN_VERSION, true );
	}

	/**
	 * Register styles.
	 *
	 * @return void
	 */
	public function register_styles() {
		$admin_style    = WP_ERP_APP_HELPER_PLUGIN_ADMIN_ASSET . '/css/style.css';
		$frontend_style = WP_ERP_APP_HELPER_PLUGIN_PUBLIC_ASSET . '/css/style.css';

		wp_register_style( 'wp_erp_app_helper_admin_style', $admin_style, array(), WP_ERP_APP_HELPER_PLUGIN_VERSION );
		wp_register_style( 'wp_erp_app_helper_style', $frontend_style, array(), WP_ERP_APP_HELPER_PLUGIN_VERSION );
		wp_register_style( 'wp_erp_app_helper_standup_tracker_style', WP_ERP_APP_HELPER_PLUGIN_ADMIN_ASSET . '/css/standup-tracker.css', array(), WP_ERP_APP_HELPER_PLUGIN_VERSION );
	}

	/**
	 * Enqueue admin scripts.
	 *
	 * @return void
	 */
	public function enqueue_admin_scripts() {
		wp_enqueue_script( 'wp_erp_app_helper_admin_script' );
		wp_localize_script(
			'wp_erp_app_helper_admin_script',
			'Wp_Erp_App_Helper_Admin',
			array()
		);

		$screen = get_current_screen();

		if ( $screen && $screen->id === 'wp-erp_page_erp-hr' ) {
			wp_enqueue_style( 'wp_erp_app_helper_standup_tracker_style' );
			wp_enqueue_script( 'wp_erp_app_helper_standup_tracker_script' );
			wp_localize_script(
				'wp_erp_app_helper_standup_tracker_script',
				'Wp_Erp_Standup_Tracker',
				array(
					'restUrl' => rest_url( 'erp-app/v1/standup' ),
					'nonce'   => wp_create_nonce( 'wp_rest' ),
				)
			);
		}
	}

	/**
	 * Enqueue front-end scripts.
	 *
	 * @return void
	 */
	public function enqueue_front_scripts() {
		wp_enqueue_script( 'wp_erp_app_helper_script' );
		wp_localize_script(
			'wp_erp_app_helper_script',
			'Wp_Erp_App_Helper',
			array()
		);
	}
}
