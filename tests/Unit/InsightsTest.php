<?php
/**
 * @package Consentaro
 */

declare(strict_types=1);

namespace Consentaro\Tests\Unit;

use Brain\Monkey\Functions;
use Consentaro\Admin\Insights;
use Consentaro\Tests\TestCase;
use Mockery;

final class InsightsTest extends TestCase {

	/**
	 * Same "now" the TestCase's current_datetime() stub resolves to.
	 */
	private function today(): string {
		return ( new \DateTimeImmutable( 'now', new \DateTimeZone( 'UTC' ) ) )->format( 'Y-m-d' );
	}

	public function test_record_creates_a_fresh_entry_for_a_new_day(): void {
		$today = $this->today();

		Functions\expect( 'get_option' )
			->once()
			->with( 'consentaro_stats_daily', array() )
			->andReturn( array() );

		Functions\expect( 'update_option' )
			->once()
			->with(
				'consentaro_stats_daily',
				Mockery::on(
					static function ( $saved ) use ( $today ) {
						return array( $today ) === array_keys( $saved )
							&& array( 'accepted' => 0, 'denied' => 0, 'customized' => 1 ) === $saved[ $today ];
					}
				),
				false
			);

		( new Insights() )->record( 'customized' );
		$this->addToAssertionCount( 1 ); // Real assertion is the Mockery expectation above.
	}

	public function test_record_increments_the_same_days_counter_not_a_new_key(): void {
		$today = $this->today();

		Functions\expect( 'get_option' )
			->once()
			->with( 'consentaro_stats_daily', array() )
			->andReturn( array( $today => array( 'accepted' => 2, 'denied' => 0, 'customized' => 5 ) ) );

		Functions\expect( 'update_option' )
			->once()
			->with(
				'consentaro_stats_daily',
				Mockery::on(
					static function ( $saved ) use ( $today ) {
						return array( $today ) === array_keys( $saved ) // still only one key for today.
							&& 3 === $saved[ $today ]['accepted']
							&& 5 === $saved[ $today ]['customized']; // untouched bucket unchanged.
					}
				),
				false
			);

		( new Insights() )->record( 'accepted' );
		$this->addToAssertionCount( 1 ); // Real assertion is the Mockery expectation above.
	}

	public function test_record_prunes_entries_older_than_90_days(): void {
		$today       = $this->today();
		$old_date    = ( new \DateTimeImmutable( 'now', new \DateTimeZone( 'UTC' ) ) )->modify( '-100 days' )->format( 'Y-m-d' );
		$recent_date = ( new \DateTimeImmutable( 'now', new \DateTimeZone( 'UTC' ) ) )->modify( '-10 days' )->format( 'Y-m-d' );

		Functions\expect( 'get_option' )
			->once()
			->with( 'consentaro_stats_daily', array() )
			->andReturn(
				array(
					$old_date    => array( 'accepted' => 1, 'denied' => 0, 'customized' => 0 ),
					$recent_date => array( 'accepted' => 1, 'denied' => 0, 'customized' => 0 ),
				)
			);

		Functions\expect( 'update_option' )
			->once()
			->with(
				'consentaro_stats_daily',
				Mockery::on(
					static function ( $saved ) use ( $old_date, $recent_date, $today ) {
						return ! array_key_exists( $old_date, $saved )
							&& array_key_exists( $recent_date, $saved )
							&& array_key_exists( $today, $saved );
					}
				),
				false
			);

		( new Insights() )->record( 'denied' );
		$this->addToAssertionCount( 1 ); // Real assertion is the Mockery expectation above.
	}

	public function test_get_range_zero_fills_every_day_including_gaps(): void {
		$populated_date = ( new \DateTimeImmutable( 'now', new \DateTimeZone( 'UTC' ) ) )->modify( '-2 days' )->format( 'Y-m-d' );

		Functions\expect( 'get_option' )
			->once()
			->with( 'consentaro_stats_daily', array() )
			->andReturn(
				array(
					$populated_date => array( 'accepted' => 4, 'denied' => 1, 'customized' => 0 ),
				)
			);

		$range = ( new Insights() )->getRange( 5 );

		$this->assertCount( 5, $range );

		// Ascending: oldest first, today last.
		$dates = array_column( $range, 'date' );
		$sorted = $dates;
		sort( $sorted );
		$this->assertSame( $sorted, $dates );
		$this->assertSame( $this->today(), $dates[ count( $dates ) - 1 ] );

		foreach ( $range as $day ) {
			if ( $day['date'] === $populated_date ) {
				$this->assertSame( 4, $day['accepted'] );
				$this->assertSame( 1, $day['denied'] );
				$this->assertSame( 0, $day['customized'] );
			} else {
				$this->assertSame( 0, $day['accepted'] );
				$this->assertSame( 0, $day['denied'] );
				$this->assertSame( 0, $day['customized'] );
			}
		}
	}

	public function test_record_ignores_an_unknown_bucket(): void {
		Functions\expect( 'get_option' )->never();
		Functions\expect( 'update_option' )->never();

		( new Insights() )->record( 'not-a-real-bucket' );

		$this->assertTrue( true ); // Reaching here without a Mockery failure is the assertion.
	}
}
