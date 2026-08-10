<?php
/**
 * Known third-party tracker signatures used to auto-categorize scripts.
 *
 * @package Consentaro
 */

declare(strict_types=1);

namespace Consentaro\Integration;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Static signature list (domain/path fragment => category) plus lookup helpers.
 *
 * Conservative by design: anything not matched here is left untouched
 * (treated as "necessary") rather than risk breaking an unrecognized script.
 */
final class TrackerSignatures {

	/**
	 * Categories we recognize, in priority order for docs/UI.
	 *
	 * @var string[]
	 */
	public const CATEGORIES = array( 'necessary', 'analytics', 'marketing', 'personalization' );

	/**
	 * Domain/path fragment => category map.
	 *
	 * Consentaro's own GTM loader is deliberately excluded — that flow is
	 * already consent-gated end-to-end by the GTM integration class.
	 *
	 * @return array<string, string>
	 */
	public static function signatures(): array {
		$map = array(
			// Analytics.
			'google-analytics.com'       => 'analytics',
			'analytics.google.com'       => 'analytics',
			'ssl.google-analytics.com'   => 'analytics',
			'hotjar.com'                 => 'analytics',
			'static.hotjar.com'          => 'analytics',
			'plausible.io'               => 'analytics',
			'cdn.plausible.io'           => 'analytics',
			'matomo.cloud'               => 'analytics',
			'clarity.ms'                 => 'analytics',
			'mixpanel.com'               => 'analytics',
			'segment.com'                => 'analytics',
			'cdn.segment.com'            => 'analytics',
			'fullstory.com'              => 'analytics',
			'amplitude.com'              => 'analytics',

			// Marketing / advertising.
			'connect.facebook.net'       => 'marketing',
			'facebook.net/en_us/fbevents' => 'marketing',
			'facebook.net'               => 'marketing',
			'ads-twitter.com'            => 'marketing',
			'static.ads-twitter.com'     => 'marketing',
			'analytics.twitter.com'      => 'marketing',
			'snap.licdn.com'             => 'marketing',
			'px.ads.linkedin.com'        => 'marketing',
			'bat.bing.com'               => 'marketing',
			'googleadservices.com'       => 'marketing',
			'googlesyndication.com'      => 'marketing',
			'doubleclick.net'            => 'marketing',
			'sc-static.net'              => 'marketing',
			'tiktok.com/i18n/pixel'      => 'marketing',
			'analytics.tiktok.com'       => 'marketing',
			'pinterest.com/ct'           => 'marketing',
			'ct.pinterest.com'           => 'marketing',
			'criteo.com'                 => 'marketing',
			'taboola.com'                => 'marketing',
			'outbrain.com'               => 'marketing',

			// Personalization / chat-embed style widgets that set preference cookies.
			'intercom.io'                => 'personalization',
			'widget.intercom.io'         => 'personalization',
			'crisp.chat'                 => 'personalization',
			'client.crisp.chat'          => 'personalization',
			'drift.com'                  => 'personalization',
			'js.driftt.com'              => 'personalization',
		);

		/**
		 * Filter the tracker signature map so developers can extend or
		 * override auto-categorization without editing plugin source.
		 *
		 * @param array<string, string> $map Domain/path fragment => category.
		 */
		$filtered = apply_filters( 'consentaro_tracker_signatures', $map );

		return is_array( $filtered ) ? $filtered : $map;
	}

	/**
	 * Content patterns for inline scripts that have no src domain to match
	 * against (e.g. a bare `fbq('init', ...)` call copy-pasted into the
	 * theme). Matched against the raw inline script body, not the URL.
	 *
	 * @return array<string, string>
	 */
	public static function contentSignatures(): array {
		$map = array(
			'fbq('        => 'marketing',
			'ttq.load('   => 'marketing',
			'ttq.track('  => 'marketing',
			'twq('        => 'marketing',
			'_linkedin_partner_id' => 'marketing',
			'pintrk('     => 'marketing',
			'_hjSettings' => 'analytics',
			'mixpanel.init(' => 'analytics',
			'amplitude.getInstance(' => 'analytics',
			'Intercom('   => 'personalization',
		);

		/**
		 * Filter content-based signatures for inline scripts.
		 *
		 * @param array<string, string> $map Content fragment => category.
		 */
		$filtered = apply_filters( 'consentaro_tracker_content_signatures', $map );

		return is_array( $filtered ) ? $filtered : $map;
	}

	/**
	 * Resolve a category for a given script identifier (src URL or a short
	 * fingerprint of inline content), checking per-site overrides first.
	 *
	 * @param string                $identifier Domain, URL, or inline fingerprint.
	 * @param array<string, string> $overrides  Site-specific identifier => category overrides.
	 */
	public static function categoryFor( string $identifier, array $overrides = array() ): string {
		$identifier = strtolower( $identifier );

		foreach ( $overrides as $needle => $category ) {
			if ( '' !== $needle && false !== strpos( $identifier, strtolower( $needle ) ) && self::isValidCategory( $category ) ) {
				return $category;
			}
		}

		foreach ( self::signatures() as $needle => $category ) {
			if ( false !== strpos( $identifier, strtolower( $needle ) ) ) {
				return $category;
			}
		}

		return 'necessary';
	}

	/**
	 * Resolve a category for an inline script: fingerprint override first,
	 * then content-pattern signatures (no src domain to match against).
	 *
	 * @param string                $content    Inline script body.
	 * @param string                $identifier Fingerprint identifier (e.g. "inline:abc123").
	 * @param array<string, string> $overrides  Site-specific identifier => category overrides.
	 */
	public static function categoryForContent( string $content, string $identifier, array $overrides = array() ): string {
		foreach ( $overrides as $needle => $category ) {
			if ( '' !== $needle && false !== strpos( $identifier, $needle ) && self::isValidCategory( $category ) ) {
				return $category;
			}
		}

		foreach ( self::contentSignatures() as $needle => $category ) {
			if ( false !== strpos( $content, $needle ) ) {
				return $category;
			}
		}

		return 'necessary';
	}

	/**
	 * Category validity check.
	 *
	 * @param string $category Candidate category.
	 */
	public static function isValidCategory( string $category ): bool {
		return in_array( $category, self::CATEGORIES, true );
	}
}
