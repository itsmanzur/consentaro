<?php
/**
 * Frontend asset loader.
 *
 * @package Consentaro
 */

declare(strict_types=1);

namespace Consentaro\Frontend;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Consentaro\Admin\Settings;
use Consentaro\Core\ServiceContainer;
use Consentaro\Integration\GeoLocation;

/**
 * Conditional enqueue for banner CSS/JS.
 */
final class Assets {

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
	 * Hooks.
	 */
	public function register(): void {
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueueFrontend' ) );
	}

	/**
	 * Load banner assets only when needed.
	 */
	public function enqueueFrontend(): void {
		/** @var GeoLocation $geo */
		$geo = $this->container->get( 'geo_location' );
		if ( ! $geo->shouldShowBanner() ) {
			return;
		}

		$version = $this->getAssetVersion();

		wp_enqueue_style(
			'consentaro-banner',
			CONSENTARO_URL . 'assets/css/banner.css',
			array(),
			$version
		);

		wp_enqueue_script(
			'consentaro-banner',
			CONSENTARO_URL . 'assets/js/banner.js',
			array(),
			$version,
			array(
				'in_footer' => true,
				'strategy'  => 'defer',
			)
		);

		wp_localize_script(
			'consentaro-banner',
			'consentaroAPI',
			array(
				'nonce' => wp_create_nonce( 'wp_rest' ),
				'url'   => esc_url_raw( rest_url( 'consentaro/v1/consent' ) ),
			)
		);

		/** @var Settings $settings_svc */
		$settings_svc = $this->container->get( 'settings' );
		$settings     = $settings_svc->getSettings();

		if ( ! empty( $settings['script_blocking'] ) ) {
			$unblock_path = CONSENTARO_PATH . 'assets/js/script-unblock.js';
			$unblock_ver  = is_readable( $unblock_path ) ? (string) filemtime( $unblock_path ) : $version;

			wp_enqueue_script(
				'consentaro-script-unblock',
				CONSENTARO_URL . 'assets/js/script-unblock.js',
				array(),
				$unblock_ver,
				array(
					'in_footer' => true,
					'strategy'  => 'defer',
				)
			);
		}
	}

	/**
	 * Filemtime-based cache bust when possible.
	 */
	public function getAssetVersion(): string {
		$js = CONSENTARO_PATH . 'assets/js/banner.js';
		if ( is_readable( $js ) ) {
			return (string) filemtime( $js );
		}

		return CONSENTARO_VERSION;
	}
}
