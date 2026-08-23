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
	'consentaro_stats_daily',
	'consentaro_consent_log_db_version',
);

foreach ( $consentaro_options as $consentaro_option ) {
	delete_option( $consentaro_option );
}

delete_transient( 'consentaro_activation_redirect' );

$consentaro_cron_timestamp = wp_next_scheduled( 'consentaro_prune_consent_log' );
if ( $consentaro_cron_timestamp ) {
	wp_unschedule_event( $consentaro_cron_timestamp, 'consentaro_prune_consent_log' );
}

// Clear geo transients (prefix consentaro_geo_).
global $wpdb;
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
$wpdb->query(
	"DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_consentaro_geo_%' OR option_name LIKE '_transient_timeout_consentaro_geo_%'"
);

// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}consentaro_consent_log" );
