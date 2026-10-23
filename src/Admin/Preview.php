<?php
/**
 * Admin-only "preview as a regulated visitor" mode for the banner.
 *
 * @package Consentaro
 */

declare(strict_types=1);

namespace Consentaro\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Lets an administrator force the banner on the front end to see exactly
 * what an EU visitor sees, without changing any setting. The URL carries a
 * nonce and is honored only for users who can manage options; it never
 * persists anything.
 */
final class Preview {

	public const QUERY_ARG = 'consentaro_preview';
	public const NONCE_KEY = 'consentaro_preview';

	/**
	 * Hooks.
	 */
	public function register(): void {
		add_filter( 'consentaro_force_banner_display', array( $this, 'maybeForce' ) );
	}

	/**
	 * Nonce'd front-end URL for the preview. Built for the current admin.
	 */
	public static function url(): string {
		return add_query_arg(
			array(
				self::QUERY_ARG => 'eu',
				'_wpnonce'      => wp_create_nonce( self::NONCE_KEY ),
			),
			home_url( '/' )
		);
	}

	/**
	 * Whether this request is a valid admin preview.
	 */
	public static function isActive(): bool {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- verified immediately below.
		if ( ! isset( $_GET[ self::QUERY_ARG ], $_GET['_wpnonce'] ) ) {
			return false;
		}

		$nonce = sanitize_text_field( wp_unslash( (string) $_GET['_wpnonce'] ) );
		// phpcs:enable

		return false !== wp_verify_nonce( $nonce, self::NONCE_KEY )
			&& current_user_can( 'manage_options' );
	}

	/**
	 * Filter callback for consentaro_force_banner_display.
	 *
	 * @param mixed $force Incoming value.
	 */
	public function maybeForce( $force ): bool {
		return (bool) $force || self::isActive();
	}
}
