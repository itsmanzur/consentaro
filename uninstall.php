<?php
/**
 * Uninstall cleanup for Consentaro.
 *
 * @package Consentaro
 */

declare(strict_types=1);

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

$consentaro_options = array(
	'consentaro_settings',
	'consentaro_gtm_id',
	'consentaro_banner_style',
	'consentaro_geo_enabled',
	'consentaro_woo_anon_queue',
);

foreach ( $consentaro_options as $consentaro_option ) {
	delete_option( $consentaro_option );
}

delete_transient( 'consentaro_activation_redirect' );

// Clear geo transients (prefix cf_geo_).
global $wpdb;
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
$wpdb->query(
	"DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_cf_geo_%' OR option_name LIKE '_transient_timeout_cf_geo_%'"
);
