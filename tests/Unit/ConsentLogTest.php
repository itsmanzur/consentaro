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
}
