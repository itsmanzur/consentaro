<?php
/**
 * @package Consentaro
 */

declare(strict_types=1);

namespace Consentaro\Tests\Unit;

use Brain\Monkey\Functions;
use Consentaro\Admin\ConsentLog;
use Consentaro\Admin\Insights;
use Consentaro\Admin\Settings;
use Consentaro\Core\ServiceContainer;
use Consentaro\Tests\TestCase;
use DateTimeImmutable;
use DateTimeZone;
use Mockery;

/**
 * Setup checklist, new banner fields, and the shared "last N days" window.
 */
final class SettingsTest extends TestCase {

	/** @var mixed */
	private $original_wpdb;

	/** @var array<string, mixed> */
	private array $stored = array();

	/** @var array<string, mixed> */
	private array $options = array();

	protected function setUp(): void {
		parent::setUp();
		$this->original_wpdb = $GLOBALS['wpdb'] ?? null;
		$this->stored        = array();
		$this->options       = array();

		Functions\when( 'get_option' )->alias(
			function ( string $name, $default = false ) {
				if ( 'consentaro_settings' === $name ) {
					return $this->stored;
				}
				return $this->options[ $name ] ?? $default;
			}
		);
		Functions\when( 'update_option' )->alias(
			function ( string $name, $value ) {
				if ( 'consentaro_settings' === $name ) {
					$this->stored = $value;
				} else {
					$this->options[ $name ] = $value;
				}
				return true;
			}
		);
		Functions\when( 'esc_url_raw' )->alias(
			static function ( $url, $protocols = null ) {
				$url = trim( (string) $url );
				return 1 === preg_match( '#^https?://[^\s]+$#i', $url ) ? $url : '';
			}
		);
		Functions\when( 'has_action' )->justReturn( false );
		Functions\when( 'wp_date' )->justReturn( '2026-10-10' );
		Functions\when( 'current_datetime' )->alias(
			static fn() => new DateTimeImmutable( '2026-10-10 15:30:00', new DateTimeZone( 'Asia/Dhaka' ) )
		);
	}

	protected function tearDown(): void {
		if ( null === $this->original_wpdb ) {
			unset( $GLOBALS['wpdb'] );
		} else {
			$GLOBALS['wpdb'] = $this->original_wpdb;
		}
		parent::tearDown();
	}

	private function settings(): Settings {
		return new Settings( new ServiceContainer() );
	}

	public function test_blank_stored_text_and_labels_fall_back_to_defaults(): void {
		$this->stored = array(
			'banner' => array(
				'text'         => '',
				'label_accept' => '   ',
			),
		);

		$banner = $this->settings()->getSettings()['banner'];

		$this->assertSame( 'We use cookies to improve your experience and measure traffic.', $banner['text'] );
		$this->assertSame( 'Accept All', $banner['label_accept'] );
		$this->assertSame( 'Deny All', $banner['label_deny'] );
	}

	public function test_gtm_id_must_be_4_to_10_alphanumerics(): void {
		$s = $this->settings();

		$this->assertContains( 'gtm_id', $s->saveSettings( array( 'gtm_id' => 'GTM-AB' ) )['rejected'] );
		$this->assertContains( 'gtm_id', $s->saveSettings( array( 'gtm_id' => 'GTM-ABCDEFGHIJK' ) )['rejected'] );

		$ok = $s->saveSettings( array( 'gtm_id' => 'gtm-abcd12' ) );
		$this->assertNotContains( 'gtm_id', $ok['rejected'] );
		$this->assertSame( 'GTM-ABCD12', $ok['gtm_id'] );
	}

	public function test_privacy_url_only_accepts_http_and_https(): void {
		$s = $this->settings();

		$bad = $s->saveSettings( array( 'banner' => array( 'privacy_url' => 'javascript:alert(1)' ) ) );
		$this->assertContains( 'banner.privacy_url', $bad['rejected'] );
		$this->assertSame( '', $bad['banner']['privacy_url'] );

		$good = $s->saveSettings( array( 'banner' => array( 'privacy_url' => 'https://example.com/privacy' ) ) );
		$this->assertSame( 'https://example.com/privacy', $good['banner']['privacy_url'] );

		$cleared = $s->saveSettings( array( 'banner' => array( 'privacy_url' => '' ) ) );
		$this->assertSame( '', $cleared['banner']['privacy_url'] );
		$this->assertNotContains( 'banner.privacy_url', $cleared['rejected'] );
	}

	public function test_privacy_page_must_be_published(): void {
		Functions\when( 'get_post_status' )->alias( static fn( $id ) => 7 === $id ? 'publish' : 'draft' );
		$s = $this->settings();

		$this->assertSame( 7, $s->saveSettings( array( 'banner' => array( 'privacy_page_id' => 7 ) ) )['banner']['privacy_page_id'] );

		$draft = $s->saveSettings( array( 'banner' => array( 'privacy_page_id' => 9 ) ) );
		$this->assertContains( 'banner.privacy_page_id', $draft['rejected'] );
		$this->assertSame( 7, $draft['banner']['privacy_page_id'] );
	}

	public function test_button_labels_are_trimmed_and_capped_at_40_chars(): void {
		$result = $this->settings()->saveSettings(
			array(
				'banner' => array(
					'label_accept' => '  Yes please  ',
					'label_deny'   => str_repeat( 'x', 80 ),
				),
			)
		);

		$this->assertSame( 'Yes please', $result['banner']['label_accept'] );
		$this->assertSame( 40, strlen( $result['banner']['label_deny'] ) );
	}

	public function test_setup_status_reports_each_item_and_completes_once_all_done(): void {
		$s = $this->settings();

		$fresh = $s->getSetupStatus( $s->getSettings() );
		$this->assertSame( 0, $fresh['done'] );
		$this->assertFalse( $fresh['complete'] );

		$result = $s->saveSettings(
			array(
				'gtm_skipped'      => true,
				'banner_confirmed' => true,
				'setup_tested'     => true,
				'banner'           => array( 'privacy_url' => 'https://example.com/privacy' ),
			)
		);

		$this->assertSame( 4, $result['setup']['done'] );
		$this->assertTrue( $result['setup']['complete'] );
		$this->assertTrue( $result['setup_complete'], 'completion is remembered for the landing tab' );
	}

	public function test_setup_item_text_counts_when_copy_differs_from_default(): void {
		$s      = $this->settings();
		$result = $s->saveSettings( array( 'banner' => array( 'text' => 'Custom cookie copy.' ) ) );

		$by_id = array_column( $result['setup']['items'], 'done', 'id' );
		$this->assertTrue( $by_id['text'] );
		$this->assertFalse( $by_id['gtm'] );
		$this->assertFalse( $by_id['privacy'] );
		$this->assertFalse( $by_id['test'] );
	}

	public function test_turning_the_log_on_records_the_start_date(): void {
		$this->settings()->saveSettings( array( 'consent_log_enabled' => true ) );

		$this->assertSame( '2026-10-10', $this->options[ ConsentLog::SINCE_OPTION ] ?? null );
	}

	public function test_range_bounds_count_today_as_day_one_in_site_timezone(): void {
		$this->assertSame(
			array(
				'from' => '2026-10-04',
				'to'   => '2026-10-10',
			),
			Insights::rangeBounds( 7 )
		);
		$this->assertSame(
			array(
				'from' => '2026-10-10',
				'to'   => '2026-10-10',
			),
			Insights::rangeBounds( 1 )
		);
	}

	public function test_log_counts_group_by_action_for_an_inclusive_window(): void {
		$this->stored = array( 'consent_log_enabled' => true );

		$wpdb         = Mockery::mock();
		$wpdb->prefix = 'wp_';
		$wpdb->shouldReceive( 'esc_like' )->andReturnArg( 0 );
		$wpdb->shouldReceive( 'get_var' )->andReturn( 'wp_consentaro_consent_log' );
		$captured = array();
		$wpdb->shouldReceive( 'prepare' )->andReturnUsing(
			static function ( $sql, ...$args ) use ( &$captured ) {
				$captured = $args;
				return $sql;
			}
		);
		if ( ! defined( 'ARRAY_A' ) ) {
			define( 'ARRAY_A', 'ARRAY_A' );
		}
		$wpdb->shouldReceive( 'get_results' )->andReturn(
			array(
				array(
					'action' => 'accept-all',
					'n'      => '5',
				),
				array(
					'action' => 'deny-all',
					'n'      => '2',
				),
				array(
					'action' => 'something-else',
					'n'      => '99',
				),
			)
		);
		$GLOBALS['wpdb'] = $wpdb;

		$counts = ( new ConsentLog() )->countByAction( '2026-10-04', '2026-10-10' );

		$this->assertSame(
			array(
				'accepted'   => 5,
				'denied'     => 2,
				'customized' => 0,
			),
			$counts
		);
		$this->assertSame( array( '2026-10-04 00:00:00', '2026-10-10 23:59:59' ), $captured );
	}

	public function test_log_counts_are_zero_and_query_free_when_logging_is_off(): void {
		$this->stored = array( 'consent_log_enabled' => false );

		$wpdb = Mockery::mock();
		$wpdb->shouldNotReceive( 'get_results' );
		$wpdb->shouldNotReceive( 'get_var' );
		$GLOBALS['wpdb'] = $wpdb;

		$this->assertSame(
			array(
				'accepted'   => 0,
				'denied'     => 0,
				'customized' => 0,
			),
			( new ConsentLog() )->countByAction( '2026-10-04', '2026-10-10' )
		);
	}
}
