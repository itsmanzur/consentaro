<?php
/**
 * @package Consentaro
 */

declare(strict_types=1);

namespace Consentaro\Tests\Unit;

use Brain\Monkey\Functions;
use Consentaro\Admin\ConsentLog;
use Consentaro\Tests\TestCase;
use Mockery;

/**
 * Most important suite in this project: it locks in the one guarantee the
 * whole Consent Log feature is built on — off by default means zero rows,
 * ever, no matter who calls record().
 */
final class ConsentLogTest extends TestCase {

	/** @var mixed */
	private $original_wpdb;

	protected function setUp(): void {
		parent::setUp();
		$this->original_wpdb = $GLOBALS['wpdb'] ?? null;
	}

	protected function tearDown(): void {
		if ( null === $this->original_wpdb ) {
			unset( $GLOBALS['wpdb'] );
		} else {
			$GLOBALS['wpdb'] = $this->original_wpdb;
		}
		parent::tearDown();
	}

	/**
	 * The lock-in test. A strict mock that fails on ANY $wpdb call (not just
	 * insert) is used deliberately — if a future refactor moves the enabled
	 * check after some other DB touch, this must still catch it.
	 */
	public function test_record_never_touches_the_database_when_logging_is_disabled(): void {
		Functions\expect( 'get_option' )
			->once()
			->with( 'consentaro_settings', array() )
			->andReturn( array( 'consent_log_enabled' => false ) );

		$wpdb = Mockery::mock();
		$wpdb->shouldNotReceive( 'insert' );
		$wpdb->shouldNotReceive( 'query' );
		$wpdb->shouldNotReceive( 'get_results' );
		$GLOBALS['wpdb'] = $wpdb;

		( new ConsentLog() )->record( 'rid-123', 'accept-all', array( 'ad_storage' => 'granted' ) );

		$this->addToAssertionCount( 1 ); // Reaching here with no Mockery failure IS the assertion.
	}

	public function test_record_never_touches_the_database_when_setting_is_entirely_absent(): void {
		// A site that has never saved settings at all — must still be a no-op,
		// not a fatal or an accidental insert from a truthy-empty-array quirk.
		Functions\expect( 'get_option' )
			->once()
			->with( 'consentaro_settings', array() )
			->andReturn( array() );

		$wpdb = Mockery::mock();
		$wpdb->shouldNotReceive( 'insert' );
		$GLOBALS['wpdb'] = $wpdb;

		( new ConsentLog() )->record( 'rid-123', 'accept-all', array( 'ad_storage' => 'granted' ) );

		$this->addToAssertionCount( 1 );
	}

	public function test_record_inserts_exactly_once_with_correct_columns_when_enabled(): void {
		Functions\expect( 'get_option' )
			->once()
			->with( 'consentaro_settings', array() )
			->andReturn( array( 'consent_log_enabled' => true ) );

		$wpdb         = Mockery::mock();
		$wpdb->prefix = 'wp_';

		$wpdb->shouldReceive( 'insert' )
			->once()
			->with(
				'wp_consentaro_consent_log',
				Mockery::on(
					static function ( $data ) {
						return 'rid-123' === $data['rid']
							&& 'accept-all' === $data['action']
							&& '{"ad_storage":"granted"}' === $data['categories']
							&& (bool) preg_match( '/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $data['created_at'] );
					}
				),
				array( '%s', '%s', '%s', '%s' )
			)
			->andReturn( 1 );

		$GLOBALS['wpdb'] = $wpdb;

		( new ConsentLog() )->record( 'rid-123', 'accept-all', array( 'ad_storage' => 'granted' ) );
		$this->addToAssertionCount( 1 ); // Real assertion is the Mockery expectation above.
	}

	/**
	 * Builds a $wpdb mock whose prepare() records (sql, args) pairs, and that
	 * answers SHOW TABLES / COUNT / SELECT. The SELECT result is returned
	 * as-is so a test can choose what rows "exist".
	 *
	 * @param array<int, array<string, string>> $rows  Rows get_results() returns.
	 * @param int                               $total COUNT(*) answer.
	 * @param array<int, array{0: string, 1: mixed}> $prepared Captured prepare() calls (by ref).
	 */
	private function mockWpdb( array $rows, int $total, array &$prepared ): void {
		if ( ! defined( 'ARRAY_A' ) ) {
			define( 'ARRAY_A', 'ARRAY_A' ); // Same value WordPress defines.
		}

		$wpdb         = Mockery::mock();
		$wpdb->prefix = 'wp_';
		$wpdb->shouldReceive( 'esc_like' )->andReturnArg( 0 );
		$wpdb->shouldReceive( 'prepare' )->andReturnUsing(
			static function ( $sql, $args = array() ) use ( &$prepared ) {
				$prepared[] = array( $sql, $args );
				return $sql;
			}
		);
		$wpdb->shouldReceive( 'get_var' )->andReturnUsing(
			static function ( $sql ) use ( $total, &$prepared ) {
				// A COUNT(*) with nothing to bind is run without prepare();
				// record it so tests can still inspect its SQL.
				if ( str_contains( $sql, 'COUNT(*)' ) ) {
					$prepared[] = array( $sql, 'get_var' );
				}
				return str_starts_with( $sql, 'SHOW TABLES' ) ? 'wp_consentaro_consent_log' : (string) $total;
			}
		);
		$wpdb->shouldReceive( 'get_results' )->andReturn( $rows );
		$GLOBALS['wpdb'] = $wpdb;
	}

	/**
	 * First recorded COUNT(*) statement.
	 *
	 * @param array<int, array{0: string, 1: mixed}> $prepared Captured calls.
	 */
	private function countSql( array $prepared ): string {
		foreach ( $prepared as $call ) {
			if ( str_contains( $call[0], 'COUNT(*)' ) ) {
				return $call[0];
			}
		}
		return '';
	}

	public function test_query_still_returns_old_rows_when_logging_is_switched_off(): void {
		// The setting must not even be consulted: collected data stays visible.
		Functions\expect( 'get_option' )->never();

		$prepared = array();
		$this->mockWpdb(
			array(
				array(
					'rid'        => 'old-1',
					'action'     => 'accept-all',
					'categories' => '{}',
					'created_at' => '2026-01-01 10:00:00',
				),
			),
			1,
			$prepared
		);

		$result = ( new ConsentLog() )->query();

		$this->assertSame( 1, $result['total'] );
		$this->assertSame( 'old-1', $result['items'][0]['rid'] );
		$this->assertSame(
			array( 'rid', 'action', 'categories', 'created_at' ),
			array_keys( $result['items'][0] ),
			'item shape is exactly the four documented columns'
		);
	}

	public function test_query_pagination_limits_items_but_total_counts_every_match(): void {
		$prepared = array();
		$this->mockWpdb(
			array(
				array(
					'rid'        => 'a',
					'action'     => 'deny-all',
					'categories' => '{}',
					'created_at' => '2026-01-02 10:00:00',
				),
				array(
					'rid'        => 'b',
					'action'     => 'deny-all',
					'categories' => '{}',
					'created_at' => '2026-01-01 10:00:00',
				),
			),
			4,
			$prepared
		);

		$result = ( new ConsentLog() )->query(
			array(
				'per_page' => 2,
				'page'     => 1,
			)
		);

		$this->assertCount( 2, $result['items'] );
		$this->assertSame( 4, $result['total'] );

		// COUNT(*) runs without LIMIT/OFFSET; the page query binds both as %d.
		$count_sql = $this->countSql( $prepared );
		$rows_call = end( $prepared );
		$this->assertStringContainsString( 'COUNT(*)', $count_sql );
		$this->assertStringNotContainsString( 'LIMIT', $count_sql );
		$this->assertStringContainsString( 'LIMIT %d OFFSET %d', $rows_call[0] );
		$this->assertSame( array( 2, 0 ), array_slice( $rows_call[1], -2 ) );
	}

	public function test_query_action_filter_is_bound_and_unknown_actions_are_ignored(): void {
		$prepared = array();
		$this->mockWpdb( array(), 0, $prepared );

		( new ConsentLog() )->query( array( 'action' => 'deny-all' ) );
		$with = end( $prepared );
		$this->assertStringContainsString( 'action = %s', $with[0] );
		$this->assertContains( 'deny-all', $with[1] );

		$prepared = array();
		$this->mockWpdb( array(), 0, $prepared );
		( new ConsentLog() )->query( array( 'action' => "x' OR '1'='1" ) );
		$without = end( $prepared );
		$this->assertStringNotContainsString( 'action = %s', $without[0] );
	}

	public function test_query_orderby_is_whitelisted_and_never_interpolates_raw_input(): void {
		foreach ( array( '1=1', 'created_at; DROP TABLE wp_users', 'id', '' ) as $hostile ) {
			$prepared = array();
			$this->mockWpdb( array(), 0, $prepared );

			( new ConsentLog() )->query(
				array(
					'orderby' => $hostile,
					'order'   => 'ASC',
				)
			);

			$sql = end( $prepared )[0];
			$this->assertStringContainsString( 'ORDER BY created_at ASC', $sql, "fallback for orderby={$hostile}" );
			$this->assertStringNotContainsString( 'DROP', $sql );
			$this->assertStringNotContainsString( 'ORDER BY 1=1', $sql );
		}

		// Whitelisted columns pass through; a bogus order falls back to DESC.
		$prepared = array();
		$this->mockWpdb( array(), 0, $prepared );
		( new ConsentLog() )->query(
			array(
				'orderby' => 'rid',
				'order'   => 'sideways',
			)
		);
		$this->assertStringContainsString( 'ORDER BY rid DESC', end( $prepared )[0] );
	}

	public function test_register_adds_the_query_filter_and_provider_delegates_to_query(): void {
		\Brain\Monkey\Filters\expectAdded( 'consentaro_consent_log_query' )->once();
		\Brain\Monkey\Actions\expectAdded( ConsentLog::CRON_HOOK )->once();

		$log = new ConsentLog();
		$log->register();

		$prepared = array();
		$this->mockWpdb( array(), 0, $prepared );

		$this->assertSame(
			array(
				'items' => array(),
				'total' => 0,
			),
			$log->provideQuery( null, array( 'per_page' => 5 ) )
		);
	}

	public function test_query_returns_empty_without_error_when_the_table_is_missing(): void {
		Functions\expect( 'get_option' )->andReturn( array( 'consent_log_enabled' => true ) );

		$wpdb         = Mockery::mock();
		$wpdb->prefix = 'wp_';
		$wpdb->shouldReceive( 'esc_like' )->andReturnArg( 0 );
		$wpdb->shouldReceive( 'prepare' )->andReturnArg( 0 );
		$wpdb->shouldReceive( 'get_var' )->once()->andReturn( null ); // SHOW TABLES finds nothing.
		$wpdb->shouldNotReceive( 'get_results' );
		$GLOBALS['wpdb'] = $wpdb;

		$this->assertSame(
			array(
				'items' => array(),
				'total' => 0,
			),
			( new ConsentLog() )->query()
		);
	}

	public function test_query_builds_a_filtered_paginated_prepared_query_and_whitelists_inputs(): void {
		Functions\expect( 'get_option' )->andReturn( array( 'consent_log_enabled' => true ) );

		if ( ! defined( 'ARRAY_A' ) ) {
			define( 'ARRAY_A', 'ARRAY_A' ); // Same value WordPress defines.
		}

		$prepared     = array();
		$wpdb         = Mockery::mock();
		$wpdb->prefix = 'wp_';
		$wpdb->shouldReceive( 'esc_like' )->andReturnArg( 0 );
		$wpdb->shouldReceive( 'prepare' )->andReturnUsing(
			static function ( $sql, $args = array() ) use ( &$prepared ) {
				$prepared[] = array( $sql, $args );
				return $sql;
			}
		);
		$wpdb->shouldReceive( 'get_var' )->andReturnUsing(
			static function ( $sql ) {
				return str_starts_with( $sql, 'SHOW TABLES' ) ? 'wp_consentaro_consent_log' : '3';
			}
		);
		$wpdb->shouldReceive( 'get_results' )->andReturn(
			array(
				array(
					'id'         => '1',
					'rid'        => 'abc',
					'action'     => 'deny-all',
					'categories' => '{}',
					'created_at' => '2026-01-01 10:00:00',
				),
			)
		);
		$GLOBALS['wpdb'] = $wpdb;

		$result = ( new ConsentLog() )->query(
			array(
				'page'       => 3,
				'per_page'   => 9999,                       // Capped to 100.
				'action'     => 'deny-all',
				'date_from'  => '2026-01-01',
				'date_to'    => '2026-02-30',               // Not a real date: ignored.
				'rid_search' => 'abc',
				'orderby'    => 'created_at; DROP TABLE x', // Not whitelisted: falls back.
				'order'      => 'sideways',                 // Not ASC: falls back to DESC.
			)
		);

		$this->assertSame( 3, $result['total'] );
		$this->assertCount( 1, $result['items'] );

		$rows_call = end( $prepared );
		$this->assertStringContainsString( 'action = %s', $rows_call[0] );
		$this->assertStringContainsString( 'created_at >= %s', $rows_call[0] );
		$this->assertStringNotContainsString( 'created_at <= %s', $rows_call[0] );
		$this->assertStringContainsString( 'rid LIKE %s', $rows_call[0] );
		$this->assertStringContainsString( 'ORDER BY created_at DESC, id DESC', $rows_call[0] );
		$this->assertStringNotContainsString( 'DROP', $rows_call[0] );
		$this->assertSame(
			array( 'deny-all', '2026-01-01 00:00:00', '%abc%', 100, 200 ),
			$rows_call[1]
		);
	}
}
