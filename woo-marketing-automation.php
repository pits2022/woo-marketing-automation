<?php
/**
 * Plugin Name: WooCommerce Marketing Automation
 * Plugin URI:  https://github.com/pits2022/woo-marketing-automation/
 * Description: Automated marketing emails and Sendy newsletter subscription for WooCommerce.
 * Version:     1.0.0
 * Requires at least: 6.9
 * Requires PHP: 8.1
 * Author:      Professional IT Services
 * Author URI:  https://www.professional-it-services.com/
 * License:     GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: woo-marketing-automation
 * Domain Path: /languages
 * WC requires at least: 10.7.0
 * WC tested up to:      10.7.0
 */

defined( 'ABSPATH' ) || exit;

define( 'WMA_VERSION',     '1.0.0' );
define( 'WMA_PLUGIN_DIR',  plugin_dir_path( __FILE__ ) );
define( 'WMA_PLUGIN_URL',  plugin_dir_url( __FILE__ ) );
define( 'WMA_PLUGIN_FILE', __FILE__ );

foreach ( [
	'class-wma-activator',
	'class-wma-settings',
	'class-wma-logger',
	'class-wma-sendy',
	'class-wma-coupon',
	'class-wma-email',
	'class-wma-shortcode',
	'class-wma-customer-lists',
	'class-wma-cron',
	'class-wma-admin',
] as $file ) {
	require_once WMA_PLUGIN_DIR . 'includes/' . $file . '.php';
}

register_activation_hook( WMA_PLUGIN_FILE,   [ 'WMA_Activator', 'activate' ] );
register_deactivation_hook( WMA_PLUGIN_FILE, [ 'WMA_Activator', 'deactivate' ] );
register_uninstall_hook( WMA_PLUGIN_FILE,    [ 'WMA_Activator', 'uninstall' ] );

add_action( 'plugins_loaded', static function () {
	if ( ! defined( 'WC_VERSION' ) || version_compare( WC_VERSION, '10.7.0', '<' ) ) {
		add_action( 'admin_notices', static function () {
			echo '<div class="error"><p>'
				. esc_html__( 'WooCommerce Marketing Automation requires WooCommerce 10.7.0 or higher.', 'woo-marketing-automation' )
				. '</p></div>';
		} );
		return;
	}

	load_plugin_textdomain(
		'woo-marketing-automation',
		false,
		dirname( plugin_basename( WMA_PLUGIN_FILE ) ) . '/languages'
	);

	WMA_Shortcode::init();
	WMA_Customer_Lists::init();
	WMA_Cron::init();
	WMA_Admin::init();
} );
