<?php
/**
 * Activation handler.
 *
 * @package Consentaro
 */

declare(strict_types=1);

namespace Consentaro\Core;

/**
 * Runs on plugin activation.
 */
final class Activator {

	/**
	 * Seed default options.
	 */
	public static function activate(): void {
		if ( false === get_option( 'consentaro_settings' ) ) {
			add_option(
				'consentaro_settings',
				array(
					'enabled'     => true,
					'gtm_id'      => '',
					'geo_enabled' => true,
					'banner'      => array(
						'position'         => 'bottom',
						'text'             => __( 'We use cookies to improve your experience and measure traffic.', 'consentaro' ),
						'bg'               => '#ffffff',
						'text_color'       => '#1a1a1a',
						'btn_primary_bg'   => '#0073aa',
						'btn_primary_text' => '#ffffff',
					),
				),
				'',
				'yes'
			);
		}

		// Skip on bulk/network activation so only a single-plugin activation
		// by an admin triggers the onboarding redirect.
		if ( ! isset( $_GET['activate-multi'] ) && ! ( is_multisite() && is_network_admin() ) ) {
			set_transient( 'consentaro_activation_redirect', 1, 30 );
		}

		flush_rewrite_rules();
	}
}
