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

	public function test_query_returns_empty_and_never_touches_the_database_when_logging_is_disabled(): void {
		Functions\expect( 'get_option' )
			->once()
			->with( 'consentaro_settings', array() )
			->andReturn( array( 'consent_log_enabled' => false ) );

		$wpdb = Mockery::mock();
		$wpdb->shouldNotReceive( 'get_var' );
		$wpdb->shouldNotReceive( 'get_results' );
		$wpdb->shouldNotReceive( 'prepare' );
		$GLOBALS['wpdb'] = $wpdb;

		$this->assertSame(
			array(
				'items' => array(),
				'total' => 0,
			),
			( new ConsentLog() )->query( array( 'page' => 2 ) )
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
