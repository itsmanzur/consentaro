<?php
/**
 * @package Consentaro
 */

declare(strict_types=1);

namespace Consentaro\Tests\Performance;

use Brain\Monkey\Functions;
use Consentaro\Core\ServiceContainer;
use Consentaro\Integration\ScriptBlocker;
use Consentaro\Tests\TestCase;

/**
 * Stress tests for ScriptBlocker::filterOutput() against large, messy pages
 * and against the regex engine's own failure mode. These are soft budgets,
 * not strict millisecond assertions — CI hardware varies — with the actual
 * measured numbers written to STDERR so a human can eyeball real values.
 */
final class ScriptBlockerPerformanceTest extends TestCase {

	private function makeBlocker(): ScriptBlocker {
		Functions\when( 'get_option' )->justReturn( array() );
		Functions\when( 'update_option' )->justReturn( true );

		$container = new ServiceContainer();
		$container->set(
			'settings',
			static function () {
				return new class {
					public function getSettings(): array {
						return array( 'script_overrides' => array() );
					}
				};
			}
		);

		return new ScriptBlocker( $container );
	}

	/**
	 * A synthetic page mixing known tracker domains, unknown/unrecognized
	 * domains, large inline scripts, and a handful of malformed/edge-case
	 * tags — the messy shape a genuinely large real-world page tends to have.
	 */
	private function buildSyntheticPage( int $known_trackers, int $unknown_scripts, int $large_inline, int $malformed ): string {
		$known = array(
			'https://www.google-analytics.com/analytics.js',
			'https://connect.facebook.net/en_US/fbevents.js',
			'https://static.hotjar.com/c/hotjar-123.js',
			'https://cdn.segment.com/analytics.js/v1/abc/analytics.min.js',
			'https://bat.bing.com/bat.js',
		);
		$unknown = array(
			'https://cdn.example-theme.com/vendor/slick.min.js',
			'https://mysite.test/wp-content/themes/mytheme/js/app.js',
			'https://cdn.unknown-widget.io/embed.js',
		);

		$html = "<!DOCTYPE html>\n<html><head>\n";

		for ( $i = 0; $i < $known_trackers; $i++ ) {
			$src   = $known[ $i % count( $known ) ] . '?cachebust=' . $i;
			$html .= '<script src="' . $src . '" async></script>' . "\n";
		}

		for ( $i = 0; $i < $unknown_scripts; $i++ ) {
			$src   = $unknown[ $i % count( $unknown ) ] . '?v=' . $i;
			$html .= '<script src="' . $src . '"></script>' . "\n";
		}

		for ( $i = 0; $i < $large_inline; $i++ ) {
			// ~3KB of plausible inline JS per tag.
			$body  = "var config_{$i} = {};\n";
			$body .= str_repeat( "config_{$i}.option_" . $i . '_x = ' . $i . ";\n", 60 );
			$html .= '<script>' . $body . '</script>' . "\n";
		}

		// A handful of malformed / edge-case tags, deliberately mixed in.
		for ( $i = 0; $i < $malformed; $i++ ) {
			switch ( $i % 4 ) {
				case 0:
					// A string literal inside the script containing "</script" as text.
					$html .= "<script>var s = 'not a real close: <\\/script tag inside a string';</script>\n";
					break;
				case 1:
					// Unquoted src, extra whitespace, mixed-case tag.
					$html .= "<SCRIPT   src=https://google-analytics.com/ga.js   async ></SCRIPT>\n";
					break;
				case 2:
					$html .= '<script type="module" src="https://connect.facebook.net/mod.js"></script>' . "\n";
					break;
				default:
					$html .= "<script></script>\n";
			}
		}

		$html .= '</head><body><p>content</p></body></html>';

		return $html;
	}

	public function test_processes_a_large_mixed_page_within_a_reasonable_time_and_memory_budget(): void {
		$blocker   = $this->makeBlocker();
		$html      = $this->buildSyntheticPage( 150, 150, 100, 40 );
		$tag_count = substr_count( $html, '<script' ) + substr_count( $html, '<SCRIPT' );
		$this->assertGreaterThanOrEqual( 400, $tag_count, 'synthetic page should contain 400+ script tags' );

		$start = microtime( true );
		$result = $blocker->filterOutput( $html );
		$elapsed_ms = ( microtime( true ) - $start ) * 1000;
		$peak_mem   = memory_get_peak_usage( true );

		fwrite(
			STDERR,
			sprintf(
				"\n[ScriptBlocker perf] %d script tags, %s KB input -> %.2f ms, peak memory %.2f MB\n",
				$tag_count,
				number_format( strlen( $html ) / 1024, 1 ),
				$elapsed_ms,
				$peak_mem / 1024 / 1024
			)
		);

		$this->assertNotSame( '', $result );
		$this->assertStringContainsString( 'data-consentaro-src', $result, 'known trackers should have been neutralized' );
		$this->assertStringContainsString( 'https://cdn.example-theme.com', $result, 'unrecognized scripts must be left untouched' );

		// Soft budget — not asserting a strict CI-fragile millisecond figure,
		// just that it comfortably finishes within an ordinary request.
		$this->assertLessThan( 2000, $elapsed_ms, 'should finish well within a couple of seconds even on a slow CI runner' );

		$memory_limit_bytes = $this->phpMemoryLimitBytes();
		if ( null !== $memory_limit_bytes ) {
			$this->assertLessThan(
				$memory_limit_bytes * 0.5,
				$peak_mem,
				'should use well under half of memory_limit for a single large page'
			);
		}
	}

	/**
	 * Deterministically forces preg_replace_callback() to hit
	 * PREG_BACKTRACK_LIMIT_ERROR (and so return null) by dropping
	 * pcre.backtrack_limit to the floor for this one call, rather than
	 * hoping a hand-crafted "pathological" HTML string reliably breaks the
	 * regex engine on every PHP build/PCRE version. This exercises the same
	 * `null === $rewritten` fail-open branch a real pathological page would.
	 */
	public function test_fail_open_returns_original_html_unchanged_when_the_regex_engine_cannot_complete(): void {
		$blocker = $this->makeBlocker();
		$html    = $this->buildSyntheticPage( 20, 20, 5, 5 );

		$original_backtrack_limit = ini_get( 'pcre.backtrack_limit' );
		ini_set( 'pcre.backtrack_limit', '1' );

		try {
			$result = $blocker->filterOutput( $html );
		} finally {
			ini_set( 'pcre.backtrack_limit', false !== $original_backtrack_limit ? $original_backtrack_limit : '1000000' );
		}

		$this->assertSame(
			$html,
			$result,
			'fail-open path must return the original HTML byte-for-byte — no fatal, no partial rewrite'
		);
	}

	private function phpMemoryLimitBytes(): ?int {
		$limit = ini_get( 'memory_limit' );
		if ( false === $limit || '-1' === $limit || '' === $limit ) {
			return null;
		}

		$unit  = strtolower( substr( $limit, -1 ) );
		$value = (int) $limit;

		return match ( $unit ) {
			'g' => $value * 1024 * 1024 * 1024,
			'm' => $value * 1024 * 1024,
			'k' => $value * 1024,
			default => $value,
		};
	}
}
