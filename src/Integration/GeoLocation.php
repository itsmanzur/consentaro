<?php
/**
 * Geo detection with Cloudflare → server → ip-api fallback.
 *
 * @package Consentaro
 */

declare(strict_types=1);

namespace Consentaro\Integration;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Consentaro\Consent\ConsentManager;

/**
 * Resolves visitor country for banner gating.
 */
final class GeoLocation {

	/**
	 * EEA + UK + CH country codes (blueprint list).
	 *
	 * @var string[]
	 */
	private const EU_COUNTRIES = array(
		'AT', 'BE', 'BG', 'HR', 'CY', 'CZ', 'DK', 'EE', 'FI', 'FR',
		'DE', 'GR', 'HU', 'IE', 'IT', 'LV', 'LT', 'LU', 'MT', 'NL',
		'PL', 'PT', 'RO', 'SK', 'SI', 'ES', 'SE', 'IS', 'LI', 'NO', 'CH', 'GB',
	);

	/**
	 * Cached country for this request.
	 *
	 * @var string|null|false Null = unset, false = unknown.
	 */
	private string|null|false $country = null;

	/**
	 * Two-letter country code or null when unknown.
	 */
	public function getCountryCode(): ?string {
		/**
		 * Override detected country (e.g. for testing).
		 *
		 * @param string|null $country Country code.
		 */
		$filtered = apply_filters( 'consentaro_user_geo_country', null );
		if ( is_string( $filtered ) && '' !== $filtered ) {
			return strtoupper( $filtered );
		}

		if ( null !== $this->country ) {
			return false === $this->country ? null : $this->country;
		}

		// Level 1: Cloudflare.
		if ( ! empty( $_SERVER['HTTP_CF_IPCOUNTRY'] ) ) {
			$code = strtoupper( sanitize_text_field( wp_unslash( $_SERVER['HTTP_CF_IPCOUNTRY'] ) ) );
			if ( 'XX' !== $code && 'T1' !== $code ) {
				$this->country = $code;
				return $this->country;
			}
		}

		// Level 2: server GeoIP module.
		if ( ! empty( $_SERVER['GEOIP_COUNTRY_CODE'] ) ) {
			$this->country = strtoupper( sanitize_text_field( wp_unslash( $_SERVER['GEOIP_COUNTRY_CODE'] ) ) );
			return $this->country;
		}

		// Level 3: ip-api.com with transient cache.
		$from_api = $this->fetchFromAPI();
		$this->country = $from_api ?? false;

		return $from_api;
	}

	/**
	 * Whether visitor is in regulated EU/EEA/UK/CH set.
	 */
	public function isEU(): bool {
		$country = $this->getCountryCode();
		return $country && in_array( $country, self::EU_COUNTRIES, true );
	}

	/**
	 * Whether the consent banner should render.
	 */
	public function shouldShowBanner(): bool {
		/**
		 * Force banner display regardless of geo/consent.
		 *
		 * @param bool $force Force display.
		 */
		if ( apply_filters( 'consentaro_force_banner_display', false ) ) {
			return true;
		}

		if ( ! empty( $_COOKIE[ ConsentManager::COOKIE_NAME ] ) ) {
			return false;
		}

		$settings = get_option( 'consentaro_settings', array() );
		if ( is_array( $settings ) && isset( $settings['enabled'] ) && empty( $settings['enabled'] ) ) {
			return false;
		}

		$geo_on = ! is_array( $settings ) || ! isset( $settings['geo_enabled'] ) || ! empty( $settings['geo_enabled'] );

		if ( ! $geo_on ) {
			return true;
		}

		$country = $this->getCountryCode();
		// Unknown geo (e.g. local) → show banner (privacy-first).
		if ( null === $country ) {
			return true;
		}

		return in_array( $country, self::EU_COUNTRIES, true );
	}

	/**
	 * Fallback lookup via ip-api.com.
	 */
	private function fetchFromAPI(): ?string {
		$ip = $this->getClientIp();

		if ( '' === $ip || $this->isLocalIp( $ip ) ) {
			return null;
		}

		$cache_key = 'consentaro_geo_' . md5( $this->getSubnet( $ip ) );
		$cached    = $this->cacheGet( $cache_key );
		if ( false !== $cached ) {
			return is_string( $cached ) ? $cached : null;
		}

		$response = wp_remote_get(
			'http://ip-api.com/json/' . rawurlencode( $ip ) . '?fields=status,countryCode',
			array(
				'timeout' => 3,
			)
		);

		if ( is_wp_error( $response ) ) {
			return null;
		}

		$body = json_decode( (string) wp_remote_retrieve_body( $response ), true );
		if ( ! is_array( $body ) || ( $body['status'] ?? '' ) !== 'success' ) {
			$this->cacheSet( $cache_key, '', HOUR_IN_SECONDS );
			return null;
		}

		$country = isset( $body['countryCode'] ) ? strtoupper( sanitize_text_field( $body['countryCode'] ) ) : null;
		if ( $country ) {
			$this->cacheSet( $cache_key, $country, DAY_IN_SECONDS );
		}

		return $country;
	}

	/**
	 * Reduces an IP address to its subnet (IPv4 /24, IPv6 /64) so that many
	 * visitors sharing an ISP/region resolve to the same cache entry instead
	 * of creating one row per unique visitor.
	 */
	private function getSubnet( string $ip ): string {
		if ( str_contains( $ip, ':' ) ) {
			$parts = explode( ':', $ip );
			return implode( ':', array_slice( $parts, 0, 4 ) ) . '::/64';
		}

		$parts = explode( '.', $ip );
		if ( 4 === count( $parts ) ) {
			$parts[3] = '0';
			return implode( '.', $parts ) . '/24';
		}

		return $ip;
	}

	/**
	 * Reads a cached value, preferring a persistent object cache (Redis/Memcached)
	 * over the options table so lookups don't accumulate rows in wp_options on
	 * sites without an external object cache.
	 *
	 * @return string|false False when not cached.
	 */
	private function cacheGet( string $key ): string|false {
		if ( wp_using_ext_object_cache() ) {
			$found = false;
			$value = wp_cache_get( $key, 'consentaro_geo', false, $found );
			return $found ? (string) $value : false;
		}

		return get_transient( $key );
	}

	/**
	 * Writes a cached value via the same object-cache-first strategy as cacheGet().
	 */
	private function cacheSet( string $key, string $value, int $ttl ): void {
		if ( wp_using_ext_object_cache() ) {
			wp_cache_set( $key, $value, 'consentaro_geo', $ttl );
			return;
		}

		set_transient( $key, $value, $ttl );
	}

	/**
	 * Best-effort client IP.
	 */
	private function getClientIp(): string {
		if ( ! empty( $_SERVER['HTTP_CF_CONNECTING_IP'] ) ) {
			return sanitize_text_field( wp_unslash( $_SERVER['HTTP_CF_CONNECTING_IP'] ) );
		}

		if ( ! empty( $_SERVER['REMOTE_ADDR'] ) ) {
			return sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) );
		}

		return '';
	}

	/**
	 * Skip API for local/dev addresses.
	 */
	private function isLocalIp( string $ip ): bool {
		return in_array( $ip, array( '127.0.0.1', '::1' ), true )
			|| str_starts_with( $ip, '192.168.' )
			|| str_starts_with( $ip, '10.' );
	}
}
