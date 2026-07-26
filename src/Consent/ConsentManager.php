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
	 * Persist consent and fire hooks.
	 *
	 * @param array<string, mixed> $consent Incoming states.
	 * @return array<string, string> Sanitized saved states.
	 */
	public function updateStates( array $consent ): array {
		$old = $this->getCurrentStates();
		$new = $this->mode->sanitizeStates( $consent );

		$this->cookies->set( self::COOKIE_NAME, $new, self::COOKIE_TTL );

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
	 * Raw stored states or null.
	 *
	 * @return array<string, string>|null
	 */
	private function getStoredStates(): ?array {
		$raw = $this->cookies->get( self::COOKIE_NAME );
		if ( ! is_array( $raw ) ) {
			return null;
		}

		return $this->mode->sanitizeStates( $raw );
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
