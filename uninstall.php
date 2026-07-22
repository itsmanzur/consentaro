<?php
/**
 * Uninstall cleanup for ConsentFlow.
 *
 * @package ConsentFlow
 */

declare(strict_types=1);

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

$consentflow_options = array(
	'consentflow_settings',
	'consentflow_gtm_id',
	'consentflow_banner_style',
	'consentflow_geo_enabled',
	'consentflow_woo_anon_queue',
);

foreach ( $consentflow_options as $consentflow_option ) {
	delete_option( $consentflow_option );
}

// Clear geo transients (prefix cf_geo_).
global $wpdb;
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
$wpdb->query(
	"DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_cf_geo_%' OR option_name LIKE '_transient_timeout_cf_geo_%'"
);
