<?php
/**
 * Consent state orchestration.
 *
 * @package Consentaro
 */

declare(strict_types=1);

namespace Consentaro\Consent;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Reads/writes consent cookie and prints Consent Mode scripts.
 */
final class ConsentManager {

	public const COOKIE_NAME = 'consentaro_state';

	/**
	 * Cookie lifetime (365 days).
	 */
	private const COOKIE_TTL = 365 * DAY_IN_SECONDS;

	/**
	 * Cookie storage.
	 *
	 * @var CookieStorage
	 */
	private CookieStorage $cookies;

	/**
	 * Consent Mode helper.
	 *
	 * @var ConsentMode
	 */
	private ConsentMode $mode;

	/**
	 * Constructor.
	 *
	 * @param CookieStorage $cookies Cookie storage.
	 * @param ConsentMode   $mode    Consent Mode helper.
	 */
	public function __construct( CookieStorage $cookies, ConsentMode $mode ) {
		$this->cookies = $cookies;
		$this->mode    = $mode;
	}

	/**
	 * Register front-end output.
	 */
	public function register(): void {
		// Priority 0 — before GTM / other trackers.
		add_action( 'wp_head', array( $this, 'printConsentScripts' ), 0 );
	}

	/**
	 * Output default (+ update if cookie present).
	 */
	public function printConsentScripts(): void {
		if ( ! $this->isEnabled() ) {
			return;
		}

		$this->mode->renderDefaultScript( $this->mode->getDefaultStates() );

		$current = $this->getStoredStates();
		if ( null !== $current ) {
			$this->mode->renderUpdateScript( $current );
		}
	}

	/**
	 * Defaults (filterable via ConsentMode).
	 *
	 * @return array<string, string>
	 */
	public function getDefaultStates(): array {
		return $this->mode->getDefaultStates();
	}

	/**
	 * Cookie states or defaults.
	 *
	 * @return array<string, string>
	 */
	public function getCurrentStates(): array {
		$stored = $this->getStoredStates();
		return $stored ?? $this->getDefaultStates();
	}

	/**
	 * Whether a specific consent type is granted.
	 *
	 * @param string $type Consent type key.
	 */
	public function hasConsent( string $type ): bool {
		$states = $this->getCurrentStates();
		return isset( $states[ $type ] ) && 'granted' === $states[ $type ];
	}

	/**
	 * Whether the visitor has already saved a choice.
	 */
	public function hasStoredConsent(): bool {
		return null !== $this->getStoredStates();
	}

	/**
	 * Non-identifying per-browser correlation token from the consent cookie,
	 * if one has been generated yet. Not tied to IP/user-agent/any other
	 * identifying data — only used to tell "this same browser changed its
	 * consent choice again later" for the optional Consent Log.
	 */
	public function getRid(): ?string {
		$raw = $this->getRawCookie();
		if ( null !== $raw && isset( $raw['rid'] ) && is_string( $raw['rid'] ) && '' !== $raw['rid'] ) {
			return $raw['rid'];
		}

		return null;
	}

	/**
	 * Persist consent and fire hooks.
	 *
	 * @param array<string, mixed> $consent Incoming states.
	 * @return array<string, string> Sanitized saved states.
	 */
	public function updateStates( array $consent ): array {
		$old = $this->getCurrentStates();
		$new = $this->mode->sanitizeStates( $consent );
		$rid = $this->getRid() ?? wp_generate_uuid4();

		$this->cookies->set(
			self::COOKIE_NAME,
			array(
				'rid'    => $rid,
				'states' => $new,
			),
			self::COOKIE_TTL
		);

		/**
		 * Fires after consent cookie is updated.
		 *
		 * @param array<string, string> $new New states.
		 * @param array<string, string> $old Previous states.
		 */
		do_action( 'consentaro_consent_updated', $new, $old );

		return $new;
	}

	/**
	 * Accept all categories.
	 *
	 * @return array<string, string>
	 */
	public function acceptAll(): array {
		return $this->updateStates( $this->mode->allGranted() );
	}

	/**
	 * Deny optional categories.
	 *
	 * @return array<string, string>
	 */
	public function denyAll(): array {
		return $this->updateStates( $this->mode->allDenied() );
	}

	/**
	 * Raw decoded cookie array, or null if unset/invalid.
	 *
	 * @return array<string, mixed>|null
	 */
	private function getRawCookie(): ?array {
		$raw = $this->cookies->get( self::COOKIE_NAME );
		return is_array( $raw ) ? $raw : null;
	}

	/**
	 * Raw stored states or null.
	 *
	 * Handles both the current cookie shape ({rid, states}) and the older
	 * flat shape (bare states map) from before the rid field existed, so
	 * upgrading the plugin doesn't reset every existing visitor's choice.
	 *
	 * @return array<string, string>|null
	 */
	private function getStoredStates(): ?array {
		$raw = $this->getRawCookie();
		if ( null === $raw ) {
			return null;
		}

		$states = isset( $raw['states'] ) && is_array( $raw['states'] ) ? $raw['states'] : $raw;

		return $this->mode->sanitizeStates( $states );
	}

	/**
	 * Plugin enabled flag from settings.
	 */
	private function isEnabled(): bool {
		$settings = get_option( 'consentaro_settings', array() );
		if ( ! is_array( $settings ) ) {
			return true;
		}
		return ! isset( $settings['enabled'] ) || ! empty( $settings['enabled'] );
	}
}
