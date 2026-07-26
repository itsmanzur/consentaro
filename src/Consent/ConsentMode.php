<?php
/**
 * Google Consent Mode v2 script generator.
 *
 * @package Consentaro
 */

declare(strict_types=1);

namespace Consentaro\Consent;

/**
 * Builds gtag consent default / update snippets.
 */
final class ConsentMode {

	/**
	 * Allowed consent type keys.
	 *
	 * @var string[]
	 */
	public const TYPES = array(
		'ad_storage',
		'analytics_storage',
		'ad_user_data',
		'ad_personalization',
		'functionality_storage',
		'personalization_storage',
		'security_storage',
	);

	/**
	 * Default denied map (functionality + security granted).
	 *
	 * @return array<string, string>
	 */
	public function getDefaultStates(): array {
		$states = array(
			'ad_storage'              => 'denied',
			'analytics_storage'       => 'denied',
			'ad_user_data'            => 'denied',
			'ad_personalization'      => 'denied',
			'functionality_storage'   => 'granted',
			'personalization_storage' => 'denied',
			'security_storage'        => 'granted',
		);

		/**
		 * Filter default Consent Mode states.
		 *
		 * @param array<string, string> $states Default states.
		 */
		$filtered = apply_filters( 'consentaro_default_consent_states', $states );

		return $this->sanitizeStates( is_array( $filtered ) ? $filtered : $states );
	}

	/**
	 * Early wp_head default consent command.
	 *
	 * @param array<string, string>|null $states Optional override.
	 */
	public function renderDefaultScript( ?array $states = null ): void {
		$payload = $this->sanitizeStates( $states ?? $this->getDefaultStates() );
		$payload['wait_for_update'] = 500;
		$json                       = wp_json_encode( $payload );

		if ( false === $json ) {
			return;
		}

		$script = sprintf(
			'window.dataLayer=window.dataLayer||[];function gtag(){dataLayer.push(arguments);}gtag(%1$s,%2$s,%3$s);',
			wp_json_encode( 'consent' ),
			wp_json_encode( 'default' ),
			$json
		);

		wp_print_inline_script_tag(
			$script,
			array(
				'data-cf-consent' => 'default',
			)
		);
	}

	/**
	 * Consent update command after user choice / cookie restore.
	 *
	 * @param array<string, string> $consents Consent map.
	 */
	public function renderUpdateScript( array $consents ): void {
		$states = $this->sanitizeStates( $consents );
		$json   = wp_json_encode( $states );

		if ( false === $json ) {
			return;
		}

		$script = sprintf(
			'gtag(%1$s,%2$s,%3$s);',
			wp_json_encode( 'consent' ),
			wp_json_encode( 'update' ),
			$json
		);

		wp_print_inline_script_tag(
			$script,
			array(
				'data-cf-consent' => 'update',
			)
		);
	}

	/**
	 * JS-ready update payload (for REST / localize).
	 *
	 * @param array<string, string> $consents Consent map.
	 * @return array<string, string>
	 */
	public function formatUpdatePayload( array $consents ): array {
		return $this->sanitizeStates( $consents );
	}

	/**
	 * Keep only known keys with granted|denied.
	 *
	 * @param array<string, mixed> $states Raw states.
	 * @return array<string, string>
	 */
	public function sanitizeStates( array $states ): array {
		$clean = array();

		foreach ( self::TYPES as $type ) {
			$value = isset( $states[ $type ] ) ? (string) $states[ $type ] : 'denied';
			$clean[ $type ] = ( 'granted' === $value ) ? 'granted' : 'denied';
		}

		return $clean;
	}

	/**
	 * Accept-all preset.
	 *
	 * @return array<string, string>
	 */
	public function allGranted(): array {
		$states = array();
		foreach ( self::TYPES as $type ) {
			$states[ $type ] = 'granted';
		}
		return $states;
	}

	/**
	 * Deny-all preset (keeps functionality + security granted).
	 *
	 * @return array<string, string>
	 */
	public function allDenied(): array {
		return $this->getDefaultStates();
	}
}
