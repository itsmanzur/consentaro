<?php
/**
 * Frontend asset loader.
 *
 * @package ConsentFlow
 */

declare(strict_types=1);

namespace ConsentFlow\Frontend;

use ConsentFlow\Core\ServiceContainer;
use ConsentFlow\Integration\GeoLocation;

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
			'consentflow-banner',
			CONSENTFLOW_URL . 'assets/css/banner.css',
			array(),
			$version
		);

		wp_enqueue_script(
			'consentflow-banner',
			CONSENTFLOW_URL . 'assets/js/banner.js',
			array(),
			$version,
			array(
				'in_footer' => true,
				'strategy'  => 'defer',
			)
		);

		wp_localize_script(
			'consentflow-banner',
			'consentflowAPI',
			array(
				'nonce' => wp_create_nonce( 'wp_rest' ),
				'url'   => esc_url_raw( rest_url( 'consentflow/v1/consent' ) ),
			)
		);
	}

	/**
	 * Filemtime-based cache bust when possible.
	 */
	public function getAssetVersion(): string {
		$js = CONSENTFLOW_PATH . 'assets/js/banner.js';
		if ( is_readable( $js ) ) {
			return (string) filemtime( $js );
		}

		return CONSENTFLOW_VERSION;
	}
}
