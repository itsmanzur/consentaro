<?php
/**
 * Full-page cache plugin compatibility.
 *
 * @package Consentaro
 */

declare(strict_types=1);

namespace Consentaro\Compatibility;

use Consentaro\Consent\ConsentManager;
use Consentaro\Core\ServiceContainer;
use Consentaro\Integration\GeoLocation;

/**
 * Ensures consent cookie / banner are not served from the wrong cache entry.
 */
final class CachePlugins {

	/**
	 * Container.
	 *
	 * @var ServiceContainer
	 */
	private ServiceContainer $container;

	/**
	 * Constructor.
	 *
	 * @param ServiceContainer $container Container.
	 */
	public function __construct( ServiceContainer $container ) {
		$this->container = $container;
	}

	/**
	 * Register cache-compat hooks.
	 */
	public function register(): void {
		$this->registerWpRocket();
		$this->registerLiteSpeed();
		$this->registerW3TotalCache();
		$this->registerGeneric();
	}

	/**
	 * WP Rocket: vary by consent cookie; do not delay banner/GTM scripts.
	 */
	private function registerWpRocket(): void {
		add_filter( 'rocket_cache_dynamic_cookies', array( $this, 'addDynamicCookie' ) );
		add_filter( 'rocket_cache_mandatory_cookies', array( $this, 'addDynamicCookie' ) );
		add_filter( 'rocket_exclude_defer_js', array( $this, 'excludeScriptsFromDefer' ) );
		add_filter( 'rocket_delay_js_exclusions', array( $this, 'excludeScriptsFromDelay' ) );
		add_filter( 'rocket_exclude_js', array( $this, 'excludeScriptsFromCombine' ) );
	}

	/**
	 * LiteSpeed Cache: vary + nocache while banner must render.
	 */
	private function registerLiteSpeed(): void {
		add_filter( 'litespeed_vary_cookies', array( $this, 'addLiteSpeedVaryCookie' ) );
		add_action( 'template_redirect', array( $this, 'maybeLiteSpeedNoCache' ), 0 );
		add_action( 'litespeed_init', array( $this, 'maybeLiteSpeedNoCache' ) );
	}

	/**
	 * W3 Total Cache: treat consent cookie as cache-variant.
	 */
	private function registerW3TotalCache(): void {
		add_filter( 'w3tc_pgcache_cookiegroups', array( $this, 'addW3tcCookieGroup' ) );
		add_action( 'template_redirect', array( $this, 'maybeW3tcNoCache' ), 0 );
	}

	/**
	 * Shared DONOTCACHE* constants when banner is required.
	 */
	private function registerGeneric(): void {
		add_action( 'template_redirect', array( $this, 'maybeSetDoNotCacheConstants' ), 0 );
	}

	/**
	 * @param array<int, string> $cookies Cookie list.
	 * @return array<int, string>
	 */
	public function addDynamicCookie( array $cookies ): array {
		$name = ConsentManager::COOKIE_NAME;
		if ( ! in_array( $name, $cookies, true ) ) {
			$cookies[] = $name;
		}
		return $cookies;
	}

	/**
	 * @param array<int, string> $cookies Cookie list.
	 * @return array<int, string>
	 */
	public function addLiteSpeedVaryCookie( array $cookies ): array {
		return $this->addDynamicCookie( $cookies );
	}

	/**
	 * Ask LiteSpeed not to cache personalized banner HTML.
	 */
	public function maybeLiteSpeedNoCache(): void {
		if ( ! $this->shouldBypassCache() ) {
			return;
		}

		// Third-party LiteSpeed API (intentionally unprefixed hook name).
		do_action( 'litespeed_control_set_nocache', 'consentaro banner' ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound
	}

	/**
	 * W3TC cookie group so pages differ with/without consent.
	 *
	 * @param array<string, mixed> $groups Cookie groups.
	 * @return array<string, mixed>
	 */
	public function addW3tcCookieGroup( array $groups ): array {
		$groups['consentaro'] = array(
			'enabled' => true,
			'cache'   => true,
			'cookies' => array( ConsentManager::COOKIE_NAME ),
		);
		return $groups;
	}

	/**
	 * Soft-bypass for W3TC when banner must show.
	 */
	public function maybeW3tcNoCache(): void {
		if ( ! $this->shouldBypassCache() ) {
			return;
		}

		if ( ! defined( 'DONOTCACHEPAGE' ) ) {
			define( 'DONOTCACHEPAGE', true ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedConstantFound
		}
	}

	/**
	 * Define common no-cache constants for other optimizers.
	 */
	public function maybeSetDoNotCacheConstants(): void {
		if ( ! $this->shouldBypassCache() ) {
			return;
		}

		if ( ! defined( 'DONOTCACHEPAGE' ) ) {
			define( 'DONOTCACHEPAGE', true ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedConstantFound
		}
		if ( ! defined( 'DONOTCACHEDB' ) ) {
			define( 'DONOTCACHEDB', true ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedConstantFound
		}
	}

	/**
	 * @param array<int, string> $exclude Patterns / paths.
	 * @return array<int, string>
	 */
	public function excludeScriptsFromDefer( array $exclude ): array {
		return array_merge( $exclude, $this->scriptPathFragments() );
	}

	/**
	 * @param array<int, string> $exclude Patterns.
	 * @return array<int, string>
	 */
	public function excludeScriptsFromDelay( array $exclude ): array {
		return array_merge( $exclude, $this->scriptPathFragments() );
	}

	/**
	 * @param array<int, string> $exclude Patterns.
	 * @return array<int, string>
	 */
	public function excludeScriptsFromCombine( array $exclude ): array {
		return array_merge( $exclude, $this->scriptPathFragments() );
	}

	/**
	 * Path fragments Rocket / optimizers match against.
	 *
	 * @return string[]
	 */
	private function scriptPathFragments(): array {
		return array(
			'consentaro/assets/js/banner',
			'consentaro/assets/js/gtm-loader',
			'consentaro/assets/js/woo',
			'data-consentaro-consent',
			'consentaroAPI',
			'consentaroGTM',
		);
	}

	/**
	 * Bypass full-page cache while the consent banner must be rendered.
	 */
	private function shouldBypassCache(): bool {
		if ( is_admin() || wp_doing_ajax() || wp_doing_cron() ) {
			return false;
		}

		/** @var GeoLocation $geo */
		$geo = $this->container->get( 'geo_location' );
		return $geo->shouldShowBanner();
	}
}
