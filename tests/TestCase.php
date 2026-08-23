<?php
/**
 * Base test case: Brain Monkey lifecycle + shared WP function stubs.
 *
 * @package Consentaro
 */

declare(strict_types=1);

namespace Consentaro\Tests;

use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase as PHPUnitTestCase;

/**
 * Every WP function stub here is a faithful pure-PHP re-implementation of a
 * simple, dependency-free WP helper (string/array transforms only) — safe to
 * share across every test file so they don't each redefine the same stubs.
 * Anything a specific test needs to behave differently (get_option,
 * update_option, $wpdb, etc.) is mocked per-test instead.
 */
abstract class TestCase extends PHPUnitTestCase {

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();
		$this->stubCommonWordPressFunctions();
	}

	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	private function stubCommonWordPressFunctions(): void {
		Functions\when( '__' )->returnArg( 1 );
		Functions\when( 'esc_html' )->returnArg( 1 );
		Functions\when( 'esc_attr' )->returnArg( 1 );
		Functions\when( 'esc_html__' )->returnArg( 1 );
		Functions\when( 'esc_attr__' )->returnArg( 1 );

		Functions\when( 'sanitize_text_field' )->alias(
			static function ( $value ): string {
				return trim( (string) preg_replace( '/<[^>]*>/', '', (string) $value ) );
			}
		);

		Functions\when( 'sanitize_key' )->alias(
			static function ( $value ): string {
				$key = strtolower( (string) $value );
				return (string) preg_replace( '/[^a-z0-9_\-]/', '', $key );
			}
		);

		Functions\when( 'wp_json_encode' )->alias( 'json_encode' );

		Functions\when( 'absint' )->alias( static fn( $value ) => abs( (int) $value ) );

		Functions\when( 'sanitize_hex_color' )->alias(
			static function ( $value ): string {
				$value = (string) $value;
				return (bool) preg_match( '/^#([A-Fa-f0-9]{3}){1,2}$/', $value ) ? $value : '';
			}
		);

		// Default: no filters registered, value passes through unchanged.
		// A specific test can still override apply_filters locally if it
		// needs to assert a filter actually ran.
		Functions\when( 'apply_filters' )->returnArg( 2 );
		Functions\when( 'do_action' )->justReturn( null );

		// Deterministic "now" in UTC — tests compute the same reference date
		// themselves (also in UTC) rather than depending on server timezone.
		Functions\when( 'current_datetime' )->alias(
			static fn() => new \DateTimeImmutable( 'now', new \DateTimeZone( 'UTC' ) )
		);
		Functions\when( 'current_time' )->alias(
			static function ( $type ) {
				$now = new \DateTimeImmutable( 'now', new \DateTimeZone( 'UTC' ) );
				return 'mysql' === $type ? $now->format( 'Y-m-d H:i:s' ) : $now->getTimestamp();
			}
		);
	}
}
