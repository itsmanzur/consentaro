<?php
/**
 * Optional, off-by-default consent decision log (GDPR "proof of consent").
 *
 * @package Consentaro
 */

declare(strict_types=1);

namespace Consentaro\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Records/reads/prunes individual consent-decision rows. Every write is
 * gated on the consent_log_enabled setting here — the single place that
 * guarantee lives, regardless of who calls record().
 */
final class ConsentLog {

	/**
	 * Daily prune cron hook name.
	 */
	public const CRON_HOOK = 'consentaro_prune_consent_log';

	/**
	 * Hard cap on rows returned by an export, to keep it a bounded request.
	 */
	private const EXPORT_LIMIT = 5000;

	/**
	 * Hooks.
	 */
	public function register(): void {
		add_action( self::CRON_HOOK, array( $this, 'prune' ) );
	}

	/**
	 * Table name helper.
	 */
	private function tableName(): string {
		global $wpdb;
		return $wpdb->prefix . 'consentaro_consent_log';
	}

	/**
	 * Whether logging is turned on in settings.
	 */
	private function isEnabled(): bool {
		$settings = get_option( 'consentaro_settings', array() );
		return is_array( $settings ) && ! empty( $settings['consent_log_enabled'] );
	}

	/**
	 * Insert one decision row — no-ops unless consent_log_enabled is true.
	 *
	 * @param string               $rid        Non-identifying per-browser token.
	 * @param string               $action     'accept-all' | 'deny-all' | 'customize'.
	 * @param array<string, mixed> $categories Granted consent state at this moment.
	 */
	public function record( string $rid, string $action, array $categories ): void {
		if ( ! $this->isEnabled() ) {
			return;
		}

		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->insert(
			$this->tableName(),
			array(
				'rid'        => sanitize_text_field( $rid ),
				'action'     => sanitize_key( $action ),
				'categories' => (string) wp_json_encode( $categories ),
				'created_at' => current_time( 'mysql' ),
			),
			array( '%s', '%s', '%s', '%s' )
		);
	}

	/**
	 * Delete rows older than the configured retention window. Runs daily
	 * via the CRON_HOOK cron event.
	 */
	public function prune(): void {
		$settings = get_option( 'consentaro_settings', array() );
		$months   = is_array( $settings ) && isset( $settings['consent_expiry_months'] )
			? (int) $settings['consent_expiry_months']
			: 12;
		$months   = max( 1, min( 24, $months ) );

		global $wpdb;
		$cutoff = current_datetime()->modify( "-{$months} months" )->format( 'Y-m-d H:i:s' );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$this->tableName()} WHERE created_at < %s", $cutoff ) );
	}

	/**
	 * CSV export of the most recent rows (capped).
	 */
	public function exportCsv(): string {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT rid, action, categories, created_at FROM {$this->tableName()} ORDER BY created_at DESC LIMIT %d",
				self::EXPORT_LIMIT
			),
			ARRAY_A
		);

		$handle = fopen( 'php://temp', 'r+' );
		if ( false === $handle ) {
			return '';
		}

		fputcsv( $handle, array( 'rid', 'action', 'categories', 'created_at' ) );
		foreach ( (array) $rows as $row ) {
			fputcsv(
				$handle,
				array(
					$row['rid'] ?? '',
					$row['action'] ?? '',
					$row['categories'] ?? '',
					$row['created_at'] ?? '',
				)
			);
		}

		rewind( $handle );
		$csv = stream_get_contents( $handle );
		fclose( $handle );

		return false !== $csv ? $csv : '';
	}
}
