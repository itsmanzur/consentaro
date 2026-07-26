<?php
/**
 * Secure cookie read/write helpers.
 *
 * @package Consentaro
 */

declare(strict_types=1);

namespace Consentaro\Consent;

/**
 * Sets, gets, and deletes Consentaro cookies.
 */
final class CookieStorage {

	/**
	 * Cookie path.
	 */
	private const PATH = '/';

	/**
	 * Persist a cookie value.
	 *
	 * @param string $name   Cookie name.
	 * @param mixed  $value  Scalar or array (JSON-encoded).
	 * @param int    $expiry Lifetime in seconds from now.
	 */
	public function set( string $name, $value, int $expiry = YEAR_IN_SECONDS ): bool {
		if ( headers_sent() ) {
			return false;
		}

		$payload = is_string( $value ) ? $value : wp_json_encode( $value );
		if ( false === $payload ) {
			return false;
		}

		$ok = setcookie(
			$name,
			$payload,
			array(
				'expires'  => time() + max( 0, $expiry ),
				'path'     => self::PATH,
				'secure'   => is_ssl(),
				'httponly' => true,
				'samesite' => 'Lax',
			)
		);

		if ( $ok ) {
			// Available in the same request for server-side reads.
			$_COOKIE[ $name ] = $payload;
		}

		return $ok;
	}

	/**
	 * Read and JSON-decode a cookie when possible.
	 *
	 * @param string $name Cookie name.
	 * @return mixed|null Decoded array/scalar, raw string, or null.
	 */
	public function get( string $name ) {
		if ( ! isset( $_COOKIE[ $name ] ) ) {
			return null;
		}

		// JSON consent blob; validated via json_decode, keys sanitized by ConsentMode.
		$raw = wp_unslash( $_COOKIE[ $name ] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		if ( ! is_string( $raw ) || '' === $raw ) {
			return null;
		}

		$decoded = json_decode( $raw, true );
		if ( JSON_ERROR_NONE === json_last_error() && is_array( $decoded ) ) {
			return $decoded;
		}

		return sanitize_text_field( $raw );
	}

	/**
	 * Remove a cookie.
	 *
	 * @param string $name Cookie name.
	 */
	public function delete( string $name ): bool {
		if ( headers_sent() ) {
			return false;
		}

		unset( $_COOKIE[ $name ] );

		return setcookie(
			$name,
			'',
			array(
				'expires'  => time() - YEAR_IN_SECONDS,
				'path'     => self::PATH,
				'secure'   => is_ssl(),
				'httponly' => true,
				'samesite' => 'Lax',
			)
		);
	}
}
