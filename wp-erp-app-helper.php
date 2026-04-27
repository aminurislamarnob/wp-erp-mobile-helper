<?php
/**
 * Plugin Name: WP ERP App Helper
 * Plugin URI:  https://welabs.dev
 * Description: Custom plugin by weLabs
 * Version: 1.0.0
 * Author: WeLabs
 * Author URI: https://welabs.dev
 * Text Domain: wp-erp-app-helper
 * WC requires at least: 5.0.0
 * Domain Path: /languages/
 * Requires Plugins:
 * License: GPL2
 */
use WeLabs\WpErpAppHelper\WpErpAppHelper;

// don't call the file directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! defined( 'WP_ERP_APP_HELPER_FILE' ) ) {
    define( 'WP_ERP_APP_HELPER_FILE', __FILE__ );
}

if ( ! defined( 'WP_ERP_APP_HELPER_BASENAME' ) ) {
    define( 'WP_ERP_APP_HELPER_BASENAME', plugin_basename( __FILE__ ) );
}

require_once __DIR__ . '/vendor/autoload.php';

/**
 * Load Wp_Erp_App_Helper Plugin when all plugins loaded
 *
 * @return \WeLabs\WpErpAppHelper\WpErpAppHelper
 */
function welabs_wp_erp_app_helper() {
    return WpErpAppHelper::init();
}

// Lets Go....
welabs_wp_erp_app_helper();
