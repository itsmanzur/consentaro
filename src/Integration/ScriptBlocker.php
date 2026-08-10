<?php
/**
 * Blocks known third-party tracking scripts until consent is granted.
 *
 * @package Consentaro
 */

declare(strict_types=1);

namespace Consentaro\Integration;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Consentaro\Admin\Settings;
use Consentaro\Core\ServiceContainer;

/**
 * Output-buffers the front-end response and neutralizes <script> tags that
 * match a known tracker signature and whose category isn't yet consented to.
 *
 * Conservative by design:
 * - Only categories with a matched signature are ever touched; anything
 *   unrecognized is left completely alone.
 * - Only runs when a consent decision is actually pending for this visitor
 *   (i.e. the banner would be shown); if no banner is needed, nothing is
 *   buffered and there is zero overhead.
 */
final class ScriptBlocker {

	/**
	 * Cap on how many distinct detections we keep for the admin UI.
	 */
	private const MAX_DETECTIONS = 200;

	/**
	 * Option name for the rolling "what did we see on the site" log.
	 */
	private const DETECTIONS_OPTION = 'consentaro_detected_scripts';

	/**
	 * Container.
	 *
	 * @var ServiceContainer
	 */
	private ServiceContainer $container;

	/**
	 * Whether any tag was actually rewritten this request (skip save if not).
	 *
	 * @var bool
	 */
	private bool $detectedSomethingNew = false;

	/**
	 * Constructor.
	 *
	 * @param ServiceContainer $container Container.
	 */
	public function __construct( ServiceContainer $container ) {
		$this->container = $container;
	}

	/**
	 * Hooks.
	 */
	public function register(): void {
		add_action( 'template_redirect', array( $this, 'maybeStartBuffer' ), 0 );
	}

	/**
	 * Decide whether to buffer this response at all.
	 */
	public function maybeStartBuffer(): void {
		if ( is_admin() || wp_doing_ajax() || wp_doing_cron() || is_feed() || $this->isRestRequest() ) {
			return;
		}

		if ( ! $this->isEnabled() ) {
			return;
		}

		/** @var GeoLocation $geo */
		$geo = $this->container->get( 'geo_location' );
		if ( ! $geo->shouldShowBanner() ) {
			// No pending decision for this visitor — nothing to gate.
			return;
		}

		/**
		 * Allow sites to opt out entirely (e.g. incompatible page builder).
		 *
		 * @param bool $enabled Whether script blocking should run.
		 */
		if ( ! apply_filters( 'consentaro_script_blocking_enabled', true ) ) {
			return;
		}

		ob_start( array( $this, 'filterOutput' ) );
	}

	/**
	 * Output buffer callback — rewrites matched script tags in the HTML.
	 *
	 * @param string $html Full page HTML.
	 */
	public function filterOutput( string $html ): string {
		if ( '' === trim( $html ) || false === stripos( $html, '<script' ) ) {
			return $html;
		}

		$overrides = $this->getOverrides();

		$rewritten = preg_replace_callback(
			'/<script\b([^>]*)>(.*?)<\/script\s*>/is',
			function ( array $matches ) use ( $overrides ): string {
				return $this->maybeRewriteTag( $matches[0], $matches[1], $matches[2], $overrides );
			},
			$html
		);

		if ( null === $rewritten ) {
			// Regex engine backtrack/memory failure — fail open, serve original HTML.
			return $html;
		}

		if ( $this->detectedSomethingNew ) {
			$this->persistDetections();
		}

		return $rewritten;
	}

	/**
	 * Inspect one <script> tag and neutralize it if it matches a tracked
	 * category. Already-neutral tags (type=application/json, our own
	 * data-consentaro-* tags, etc.) are left alone.
	 *
	 * @param string $full_tag    Full original tag including attrs + content.
	 * @param string $attrs_raw   Raw attribute string.
	 * @param string $content     Inline script body (empty for external).
	 * @param array<string,string> $overrides Site overrides.
	 */
	private function maybeRewriteTag( string $full_tag, string $attrs_raw, string $content, array $overrides ): string {
		// Never touch our own consent/GTM bootstrap tags or anything already gated.
		if ( false !== stripos( $attrs_raw, 'data-consentaro' ) ) {
			return $full_tag;
		}

		$src = $this->extractAttr( $attrs_raw, 'src' );

		if ( '' !== $src ) {
			$identifier = $src;
			$category   = TrackerSignatures::categoryFor( $identifier, $overrides );
		} elseif ( '' !== trim( $content ) ) {
			// Fingerprint inline scripts by a short hash so overrides can target them.
			$identifier = 'inline:' . substr( md5( $content ), 0, 12 );

			// Overrides on the fingerprint win first, then content-pattern match.
			$category = TrackerSignatures::categoryForContent( $content, $identifier, $overrides );
		} else {
			return $full_tag;
		}

		if ( 'necessary' === $category ) {
			return $full_tag;
		}

		$this->recordDetection( $identifier, $category, '' !== $src );

		return $this->buildNeutralizedTag( $attrs_raw, $content, $src, $category );
	}

	/**
	 * Rebuild the tag as an inert placeholder the frontend script can revive.
	 *
	 * @param string $attrs_raw Original attribute string.
	 * @param string $content   Inline content, if any.
	 * @param string $src       Original src, if any.
	 * @param string $category  Resolved category.
	 */
	private function buildNeutralizedTag( string $attrs_raw, string $content, string $src, string $category ): string {
		// Strip any existing type="" so we don't emit it twice.
		$attrs_raw = (string) preg_replace( '/\s+type\s*=\s*(["\']).*?\1/i', '', $attrs_raw );

		if ( '' !== $src ) {
			// Remove the live src so the browser never fetches it.
			$attrs_raw = (string) preg_replace( '/\s+src\s*=\s*(["\']).*?\1/i', '', $attrs_raw );
			$attrs_raw .= ' data-consentaro-src="' . esc_attr( $src ) . '"';
		}

		$attrs_raw .= ' type="text/plain" data-consentaro-category="' . esc_attr( $category ) . '"';

		return '<script' . $attrs_raw . '>' . $content . '</script>';
	}

	/**
	 * Pull a single attribute value out of a raw attribute string.
	 *
	 * @param string $attrs_raw Raw attribute string.
	 * @param string $name      Attribute name.
	 */
	private function extractAttr( string $attrs_raw, string $name ): string {
		if ( preg_match( '/\b' . preg_quote( $name, '/' ) . '\s*=\s*(["\'])(.*?)\1/i', $attrs_raw, $m ) ) {
			return $m[2];
		}
		return '';
	}

	/**
	 * Detections accumulated so far this request, lazily seeded from the
	 * stored option on first use and saved once at the end of the request.
	 *
	 * @var array<string, array<string, mixed>>
	 */
	private array $pendingDetections = array();

	/**
	 * Whether pendingDetections has been seeded from get_option() yet.
	 *
	 * @var bool
	 */
	private bool $detectionsLoaded = false;

	/**
	 * Queue a detection for the admin "Detected scripts" list.
	 *
	 * @param string $identifier Src URL or inline fingerprint.
	 * @param string $category   Resolved category.
	 * @param bool   $is_src     Whether this came from a src attribute.
	 */
	private function recordDetection( string $identifier, string $category, bool $is_src ): void {
		if ( ! $this->detectionsLoaded ) {
			$stored                  = get_option( self::DETECTIONS_OPTION, array() );
			$this->pendingDetections = is_array( $stored ) ? $stored : array();
			$this->detectionsLoaded  = true;
		}

		$existing = $this->pendingDetections;

		if ( isset( $existing[ $identifier ] ) && $existing[ $identifier ]['category'] === $category ) {
			return; // Already known, nothing changed.
		}

		if ( count( $existing ) >= self::MAX_DETECTIONS && ! isset( $existing[ $identifier ] ) ) {
			return; // Cap reached — don't grow unbounded on large sites.
		}

		$this->pendingDetections[ $identifier ] = array(
			'category'   => $category,
			'is_src'     => $is_src,
			'first_seen' => $existing[ $identifier ]['first_seen'] ?? time(),
			'last_seen'  => time(),
		);

		$this->detectedSomethingNew = true;
	}

	/**
	 * Save this request's detections (autoload off — admin-read-only data).
	 */
	private function persistDetections(): void {
		if ( empty( $this->pendingDetections ) ) {
			return;
		}
		update_option( self::DETECTIONS_OPTION, $this->pendingDetections, false );
	}

	/**
	 * Site-specific identifier => category overrides from settings.
	 *
	 * @return array<string, string>
	 */
	private function getOverrides(): array {
		/** @var Settings $settings */
		$settings = $this->container->get( 'settings' );
		$data     = $settings->getSettings();

		$overrides = $data['script_overrides'] ?? array();
		return is_array( $overrides ) ? $overrides : array();
	}

	/**
	 * Whether the plugin (and blocking specifically) is enabled.
	 */
	private function isEnabled(): bool {
		/** @var Settings $settings */
		$settings = $this->container->get( 'settings' );
		$data     = $settings->getSettings();

		if ( empty( $data['enabled'] ) ) {
			return false;
		}

		return ! isset( $data['script_blocking'] ) || ! empty( $data['script_blocking'] );
	}

	/**
	 * Best-effort REST request detection this early in the request lifecycle.
	 */
	private function isRestRequest(): bool {
		if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
			return true;
		}

		$rest_prefix = trailingslashit( rest_get_url_prefix() );
		return isset( $_SERVER['REQUEST_URI'] ) && false !== strpos( wp_unslash( (string) $_SERVER['REQUEST_URI'] ), $rest_prefix ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
	}
}
