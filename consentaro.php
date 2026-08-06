<?php
/**
 * Plugin Name:       Consentaro - Cookie Consent & Google Consent Mode for WooCommerce
 * Plugin URI:        https://wordpress.org/plugins/consentaro/
 * Description:       Ultra-light Google Consent Mode v2 for WordPress — simple banner, GTM after choice, WooCommerce-ready.
 * Version:           1.2.0
 * Requires at least: 6.6
 * Requires PHP:      8.0
 * Author:            itsmanzur
 * Author URI:        https://profiles.wordpress.org/itsmanzur/
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       consentaro
 * Domain Path:       /languages
 *
 * @package Consentaro
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'CONSENTARO_VERSION', '1.2.0' );
define( 'CONSENTARO_FILE', __FILE__ );
define( 'CONSENTARO_PATH', plugin_dir_path( __FILE__ ) );
define( 'CONSENTARO_URL', plugin_dir_url( __FILE__ ) );

require_once CONSENTARO_PATH . 'src/autoload.php';

register_activation_hook( __FILE__, array( 'Consentaro\\Core\\Activator', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'Consentaro\\Core\\Deactivator', 'deactivate' ) );

add_action(
	'plugins_loaded',
	static function (): void {
		Consentaro\Core\Plugin::get_instance()->init();
	}
);
