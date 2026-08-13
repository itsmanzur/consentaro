<?php
/**
 * Privacy-friendly daily consent decision counts.
 *
 * @package Consentaro
 */

declare(strict_types=1);

namespace Consentaro\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Records and reads anonymous daily aggregate counts — no per-visitor data,
 * IP addresses, cookie IDs, or timestamp-level logs are ever stored.
 */
final class Insights {

	/**
	 * Option name.
	 */
	private const OPTION = 'consentaro_stats_daily';

	/**
	 * Rolling window, in days, kept in the option.
	 */
	private const RETENTION_DAYS = 90;

	/**
	 * Valid count buckets.
	 */
	private const BUCKETS = array( 'accepted', 'denied', 'customized' );

	/**
	 * Bump today's counter for a bucket and prune anything past the
	 * retention window before saving.
	 *
	 * @param string $bucket 'accepted' | 'denied' | 'customized'.
	 */
	public function record( string $bucket ): void {
		if ( ! in_array( $bucket, self::BUCKETS, true ) ) {
			return;
		}

		$data = $this->getRaw();
		$date = current_datetime()->format( 'Y-m-d' );

		if ( ! isset( $data[ $date ] ) || ! is_array( $data[ $date ] ) ) {
			$data[ $date ] = array(
				'accepted'   => 0,
				'denied'     => 0,
				'customized' => 0,
			);
		}

		$data[ $date ][ $bucket ] = (int) ( $data[ $date ][ $bucket ] ?? 0 ) + 1;

		update_option( self::OPTION, $this->prune( $data ), false );
	}

	/**
	 * Last $days days of counts, ascending by date, zero-filled for gaps.
	 *
	 * @param int $days Number of days to return.
	 * @return array<int, array{date: string, accepted: int, denied: int, customized: int}>
	 */
	public function getRange( int $days ): array {
		$days = max( 1, $days );
		$data = $this->getRaw();
		$now  = current_datetime();

		$out = array();
		for ( $i = $days - 1; $i >= 0; $i-- ) {
			$date   = $now->modify( "-{$i} days" )->format( 'Y-m-d' );
			$counts = is_array( $data[ $date ] ?? null ) ? $data[ $date ] : array();

			$out[] = array(
				'date'       => $date,
				'accepted'   => (int) ( $counts['accepted'] ?? 0 ),
				'denied'     => (int) ( $counts['denied'] ?? 0 ),
				'customized' => (int) ( $counts['customized'] ?? 0 ),
			);
		}

		return $out;
	}

	/**
	 * Raw stored option, normalized to an array.
	 *
	 * @return array<string, array<string, int>>
	 */
	private function getRaw(): array {
		$data = get_option( self::OPTION, array() );
		return is_array( $data ) ? $data : array();
	}

	/**
	 * Drop any date older than the retention window.
	 *
	 * @param array<string, array<string, int>> $data Date => counts.
	 * @return array<string, array<string, int>>
	 */
	private function prune( array $data ): array {
		$cutoff = current_datetime()->modify( '-' . ( self::RETENTION_DAYS - 1 ) . ' days' )->format( 'Y-m-d' );

		foreach ( $data as $date => $counts ) {
			if ( $date < $cutoff ) {
				unset( $data[ $date ] );
			}
		}

		return $data;
	}
}
