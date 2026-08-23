<?php
/**
 * @package Consentaro
 */

declare(strict_types=1);

namespace Consentaro\Tests\Unit;

use Consentaro\Integration\TrackerSignatures;
use Consentaro\Tests\TestCase;

final class TrackerSignaturesTest extends TestCase {

	public function test_known_domain_maps_to_its_category(): void {
		$this->assertSame(
			'analytics',
			TrackerSignatures::categoryFor( 'https://www.google-analytics.com/analytics.js' )
		);
		$this->assertSame(
			'marketing',
			TrackerSignatures::categoryFor( 'https://connect.facebook.net/en_US/fbevents.js' )
		);
	}

	/**
	 * The single most important behavior in the whole class: an unrecognized
	 * script must default to 'necessary' (i.e. left alone), never silently
	 * blocked as something else. Getting this wrong breaks real scripts.
	 */
	public function test_unknown_domain_defaults_to_necessary(): void {
		$this->assertSame(
			'necessary',
			TrackerSignatures::categoryFor( 'https://cdn.example-totally-unknown-vendor.com/widget.js' )
		);
	}

	public function test_site_override_takes_precedence_over_builtin_signature(): void {
		$category = TrackerSignatures::categoryFor(
			'https://www.google-analytics.com/analytics.js',
			array( 'google-analytics.com' => 'personalization' )
		);

		$this->assertSame( 'personalization', $category );
	}

	public function test_invalid_override_category_is_ignored_falls_through_to_builtin(): void {
		$category = TrackerSignatures::categoryFor(
			'https://www.google-analytics.com/analytics.js',
			array( 'google-analytics.com' => 'not-a-real-category' )
		);

		$this->assertSame( 'analytics', $category );
	}

	public function test_category_lookup_is_case_insensitive(): void {
		$this->assertSame(
			'analytics',
			TrackerSignatures::categoryFor( 'HTTPS://WWW.GOOGLE-ANALYTICS.COM/ANALYTICS.JS' )
		);
	}

	public function test_content_pattern_match(): void {
		$content = "someOtherCode(); fbq('init', '123456789');";

		$this->assertSame(
			'marketing',
			TrackerSignatures::categoryForContent( $content, 'inline:abc123' )
		);
	}

	public function test_content_override_by_identifier_takes_precedence(): void {
		$content = "fbq('init', '123456789');"; // Would match 'marketing' by content.

		$category = TrackerSignatures::categoryForContent(
			$content,
			'inline:abc123',
			array( 'inline:abc123' => 'personalization' )
		);

		$this->assertSame( 'personalization', $category );
	}

	public function test_content_with_no_matching_pattern_defaults_to_necessary(): void {
		$this->assertSame(
			'necessary',
			TrackerSignatures::categoryForContent( 'console.log("just a theme script");', 'inline:xyz' )
		);
	}

	public function test_is_valid_category(): void {
		$this->assertTrue( TrackerSignatures::isValidCategory( 'analytics' ) );
		$this->assertTrue( TrackerSignatures::isValidCategory( 'necessary' ) );
		$this->assertFalse( TrackerSignatures::isValidCategory( 'not-a-real-category' ) );
		$this->assertFalse( TrackerSignatures::isValidCategory( '' ) );
	}
}
