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
	 * Option holding the date logging was last switched on.
	 */
	public const SINCE_OPTION = 'consentaro_consent_log_since';

	/**
	 * Hard cap on rows returned by an export, to keep it a bounded request.
	 */
	private const EXPORT_LIMIT = 5000;

	/**
	 * Hooks.
	 */
	public function register(): void {
		add_action( self::CRON_HOOK, array( $this, 'prune' ) );
		add_filter( 'consentaro_consent_log_query', array( $this, 'provideQuery' ), 10, 2 );
	}

	/**
	 * Thin filter wrapper around query(), kept separate so query() stays
	 * directly unit-testable. Used by add-ons such as Consentaro Pro.
	 *
	 * @param mixed                $default Incoming value (null).
	 * @param array<string, mixed> $args    Query args — see query().
	 * @return array{items: array<int, array<string, mixed>>, total: int}
	 */
	public function provideQuery( $default, array $args = array() ): array {
		unset( $default );
		return $this->query( $args );
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
	 * Paginated, filterable read of the log — backs the
	 * `consentaro_consent_log_query` filter that add-ons use to show the log.
	 *
	 * Supported $args: page (int, default 1), per_page (int, default 20, max
	 * 100), action ('accept-all'|'deny-all'|'customize'), date_from/date_to
	 * ('Y-m-d', inclusive), rid_search (partial rid match), orderby
	 * ('created_at'|'action'|'rid', default created_at), order ('ASC'|'DESC',
	 * default DESC).
	 *
	 * Deliberately does NOT check the consent_log_enabled setting (unlike
	 * record()): this only reads rows that were already collected, and they
	 * should stay visible even after logging has been switched off. Returns
	 * an empty result (never an error) if the table doesn't exist.
	 *
	 * @param array<string, mixed> $args Query args.
	 * @return array{items: array<int, array<string, mixed>>, total: int}
	 */
	public function query( array $args = array() ): array {
		$empty = array(
			'items' => array(),
			'total' => 0,
		);

		if ( ! $this->tableExists() ) {
			return $empty;
		}

		global $wpdb;

		$page     = max( 1, (int) ( $args['page'] ?? 1 ) );
		$per_page = max( 1, min( 100, (int) ( $args['per_page'] ?? 20 ) ) );
		$offset   = ( $page - 1 ) * $per_page;

		$where  = array( '1=1' );
		$params = array();

		$action = isset( $args['action'] ) ? sanitize_key( (string) $args['action'] ) : '';
		if ( in_array( $action, array( 'accept-all', 'deny-all', 'customize' ), true ) ) {
			$where[]  = 'action = %s';
			$params[] = $action;
		}

		$date_from = $this->validDate( $args['date_from'] ?? null );
		if ( null !== $date_from ) {
			$where[]  = 'created_at >= %s';
			$params[] = $date_from . ' 00:00:00';
		}

		$date_to = $this->validDate( $args['date_to'] ?? null );
		if ( null !== $date_to ) {
			$where[]  = 'created_at <= %s';
			$params[] = $date_to . ' 23:59:59';
		}

		$rid_search = isset( $args['rid_search'] ) ? trim( sanitize_text_field( (string) $args['rid_search'] ) ) : '';
		if ( '' !== $rid_search ) {
			$where[]  = 'rid LIKE %s';
			$params[] = '%' . $wpdb->esc_like( $rid_search ) . '%';
		}

		$orderby = isset( $args['orderby'] ) ? sanitize_key( (string) $args['orderby'] ) : 'created_at';
		if ( ! in_array( $orderby, array( 'created_at', 'action', 'rid' ), true ) ) {
			$orderby = 'created_at';
		}
		$order = isset( $args['order'] ) && 'ASC' === strtoupper( (string) $args['order'] ) ? 'ASC' : 'DESC';

		$where_sql = implode( ' AND ', $where );
		$table     = $this->tableName();

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$total_sql = "SELECT COUNT(*) FROM {$table} WHERE {$where_sql}";
		$total     = (int) $wpdb->get_var( empty( $params ) ? $total_sql : $wpdb->prepare( $total_sql, $params ) );

		// $orderby/$order are whitelisted above, so interpolating them is safe.
		$rows_sql = "SELECT rid, action, categories, created_at FROM {$table} WHERE {$where_sql} ORDER BY {$orderby} {$order}, id {$order} LIMIT %d OFFSET %d";
		$rows     = $wpdb->get_results(
			$wpdb->prepare( $rows_sql, array_merge( $params, array( $per_page, $offset ) ) ),
			ARRAY_A
		);
		// phpcs:enable

		return array(
			'items' => is_array( $rows ) ? $rows : array(),
			'total' => $total,
		);
	}

	/**
	 * Decision counts by action for an inclusive Y-m-d window (site time).
	 * Zeros when logging is off or the table is missing.
	 *
	 * @param string $from First day, 'Y-m-d'.
	 * @param string $to   Last day, 'Y-m-d'.
	 * @return array{accepted: int, denied: int, customized: int}
	 */
	public function countByAction( string $from, string $to ): array {
		$out = array(
			'accepted'   => 0,
			'denied'     => 0,
			'customized' => 0,
		);

		$from = $this->validDate( $from );
		$to   = $this->validDate( $to );
		if ( null === $from || null === $to || ! $this->isEnabled() || ! $this->tableExists() ) {
			return $out;
		}

		global $wpdb;
		$table = $this->tableName();

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT action, COUNT(*) AS n FROM {$table} WHERE created_at >= %s AND created_at <= %s GROUP BY action",
				$from . ' 00:00:00',
				$to . ' 23:59:59'
			),
			ARRAY_A
		);
		// phpcs:enable

		$map = array(
			'accept-all' => 'accepted',
			'deny-all'   => 'denied',
			'customize'  => 'customized',
		);
		foreach ( (array) $rows as $row ) {
			$action = (string) ( $row['action'] ?? '' );
			if ( isset( $map[ $action ] ) ) {
				$out[ $map[ $action ] ] = (int) ( $row['n'] ?? 0 );
			}
		}

		return $out;
	}

	/**
	 * Date logging was last turned on ('Y-m-d'), or null if unknown.
	 */
	public function enabledSince(): ?string {
		$value = get_option( self::SINCE_OPTION, '' );
		return is_string( $value ) && '' !== $value ? $value : null;
	}

	/**
	 * Whether the log table exists (it is created on activation/load, but a
	 * hand-dropped table must not turn a read into a fatal error).
	 */
	private function tableExists(): bool {
		global $wpdb;
		$table = $this->tableName();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return $table === $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $table ) ) );
	}

	/**
	 * Returns a 'Y-m-d' string only if it is a real calendar date.
	 *
	 * @param mixed $value Candidate value.
	 */
	private function validDate( $value ): ?string {
		if ( ! is_string( $value ) || 1 !== preg_match( '/^\d{4}-\d{2}-\d{2}$/', $value ) ) {
			return null;
		}
		$parsed = \DateTime::createFromFormat( 'Y-m-d', $value );
		return ( false !== $parsed && $parsed->format( 'Y-m-d' ) === $value ) ? $value : null;
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
