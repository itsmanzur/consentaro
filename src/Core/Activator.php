<?php
/**
 * Activation handler.
 *
 * @package ConsentFlow
 */

declare(strict_types=1);

namespace ConsentFlow\Core;

/**
 * Runs on plugin activation.
 */
final class Activator {

	/**
	 * Seed default options.
	 */
	public static function activate(): void {
		if ( false === get_option( 'consentflow_settings' ) ) {
			add_option(
				'consentflow_settings',
				array(
					'enabled'     => true,
					'gtm_id'      => '',
					'geo_enabled' => true,
					'banner'      => array(
						'position'         => 'bottom',
						'text'             => __( 'We use cookies to improve your experience and measure traffic.', 'consentflow' ),
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

		flush_rewrite_rules();
	}
}
