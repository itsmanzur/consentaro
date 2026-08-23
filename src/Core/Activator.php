<?php
/**
 * Activation handler.
 *
 * @package Consentaro
 */

declare(strict_types=1);

namespace Consentaro\Core;

use Consentaro\Admin\ConsentLog;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Runs on plugin activation.
 */
final class Activator {

	/**
	 * Bump when the consent_log table schema changes.
	 */
	private const CONSENT_LOG_DB_VERSION = '1.0';

	/**
	 * Seed default options.
	 */
	public static function activate(): void {
		if ( false === get_option( 'consentaro_settings' ) ) {
			add_option(
				'consentaro_settings',
				array(
					'enabled'     => true,
					'gtm_id'      => '',
					'geo_enabled' => true,
					'cf_trusted'  => false,
					'banner'      => array(
						'position'         => 'bottom',
						'text'             => __( 'We use cookies to improve your experience and measure traffic.', 'consentaro' ),
						'bg'               => '#ffffff',
						'text_color'       => '#1a1a1a',
						'btn_primary_bg'   => '#0E7C66',
						'btn_primary_text' => '#ffffff',
					),
				),
				'',
				'yes'
			);
		}

		self::maybeCreateOrUpgradeTable();
		self::scheduleCron();

		// Skip on bulk/network activation so only a single-plugin activation
		// by an admin triggers the onboarding redirect. Only presence of this
		// WP-core-set flag is checked (no value is read/trusted), so no nonce
		// applies here.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( ! isset( $_GET['activate-multi'] ) && ! ( is_multisite() && is_network_admin() ) ) {
			set_transient( 'consentaro_activation_redirect', 1, 30 );
		}

		flush_rewrite_rules();
	}

	/**
	 * Create (or, on a version bump, upgrade) the consent_log table.
	 *
	 * Called on activation, and also on every load via Plugin::init() —
	 * a normal "Update" from the Plugins screen replaces files but does
	 * NOT fire register_activation_hook, so a future schema change needs
	 * this self-healing check to actually reach already-installed sites.
	 */
	public static function maybeCreateOrUpgradeTable(): void {
		if ( get_option( 'consentaro_consent_log_db_version' ) === self::CONSENT_LOG_DB_VERSION ) {
			return;
		}

		global $wpdb;
		$table_name      = $wpdb->prefix . 'consentaro_consent_log';
		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE {$table_name} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			rid varchar(36) NOT NULL,
			action varchar(20) NOT NULL,
			categories longtext NOT NULL,
			created_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY rid (rid),
			KEY created_at (created_at)
		) {$charset_collate};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );

		update_option( 'consentaro_consent_log_db_version', self::CONSENT_LOG_DB_VERSION, false );
	}

	/**
	 * Schedule the daily consent-log prune event if not already scheduled.
	 */
	public static function scheduleCron(): void {
		if ( ! wp_next_scheduled( ConsentLog::CRON_HOOK ) ) {
			wp_schedule_event( time(), 'daily', ConsentLog::CRON_HOOK );
		}
	}
}
