<?php
/**
 * Plugin Name:       ConsentFlow
 * Plugin URI:        https://wordpress.org/plugins/consentflow/
 * Description:       Ultra-light Google Consent Mode v2 for WordPress — simple banner, GTM after choice, WooCommerce-ready.
 * Version:           1.0.0
 * Requires at least: 6.4
 * Requires PHP:      8.0
 * Author:            itsmanzur
 * Author URI:        https://profiles.wordpress.org/itsmanzur/
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       consentflow
 * Domain Path:       /languages
 *
 * @package ConsentFlow
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'CONSENTFLOW_VERSION', '1.0.0' );
define( 'CONSENTFLOW_FILE', __FILE__ );
define( 'CONSENTFLOW_PATH', plugin_dir_path( __FILE__ ) );
define( 'CONSENTFLOW_URL', plugin_dir_url( __FILE__ ) );

require_once CONSENTFLOW_PATH . 'src/autoload.php';

register_activation_hook( __FILE__, array( 'ConsentFlow\\Core\\Activator', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'ConsentFlow\\Core\\Deactivator', 'deactivate' ) );

add_action(
	'plugins_loaded',
	static function (): void {
		ConsentFlow\Core\Plugin::get_instance()->init();
	}
);
