<?php
/**
 * GTM / gtag integration with consent-aware loading.
 *
 * @package Consentaro
 */

declare(strict_types=1);

namespace Consentaro\Integration;

use Consentaro\Admin\Settings;
use Consentaro\Consent\ConsentManager;
use Consentaro\Core\ServiceContainer;

/**
 * Injects GTM only after a consent decision (or when banner is not required).
 */
final class GTM {

	/**
	 * Container.
	 *
	 * @var ServiceContainer
	 */
	private ServiceContainer $container;

	/**
	 * Whether the GTM bootstrap was printed this request.
	 *
	 * @var bool
	 */
	private bool $injected = false;

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
		// After ConsentManager (priority 0).
		add_action( 'wp_head', array( $this, 'injectScripts' ), 1 );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueueLoader' ) );
	}

	/**
	 * Print GTM snippet when allowed, otherwise bootstrap config for JS loader.
	 */
	public function injectScripts(): void {
		if ( is_admin() || $this->injected ) {
			return;
		}

		$id = $this->getContainerID();
		if ( '' === $id || ! $this->isPluginEnabled() ) {
			return;
		}

		if ( $this->shouldLoadNow() ) {
			$this->printContainerSnippet( $id );
			$this->injected = true;
			return;
		}

		// Waiting for banner choice — config only (loader listens for event).
		wp_print_inline_script_tag(
			sprintf(
				'window.consentaroGTM=window.consentaroGTM||{id:%s,loaded:false};',
				wp_json_encode( $id )
			),
			array(
				'data-cf-gtm' => 'pending',
			)
		);
	}

	/**
	 * Lightweight deferred loader when GTM is pending consent.
	 */
	public function enqueueLoader(): void {
		if ( is_admin() ) {
			return;
		}

		$id = $this->getContainerID();
		if ( '' === $id || ! $this->isPluginEnabled() ) {
			return;
		}

		if ( $this->shouldLoadNow() ) {
			return;
		}

		$path = CONSENTARO_PATH . 'assets/js/gtm-loader.js';
		$ver  = is_readable( $path ) ? (string) filemtime( $path ) : CONSENTARO_VERSION;

		wp_enqueue_script(
			'consentaro-gtm-loader',
			CONSENTARO_URL . 'assets/js/gtm-loader.js',
			array(),
			$ver,
			array(
				'in_footer' => true,
				'strategy'  => 'defer',
			)
		);
	}

	/**
	 * Filtered, validated GTM container ID.
	 */
	public function getContainerID(): string {
		/** @var Settings $settings */
		$settings = $this->container->get( 'settings' );
		$data     = $settings->getSettings();
		$id       = isset( $data['gtm_id'] ) ? strtoupper( trim( (string) $data['gtm_id'] ) ) : '';

		/**
		 * Filter GTM container ID.
		 *
		 * @param string $id Container ID.
		 */
		$id = (string) apply_filters( 'consentaro_gtm_id', $id );
		$id = strtoupper( trim( $id ) );

		if ( '' === $id || ! preg_match( '/^GTM-[A-Z0-9]+$/', $id ) ) {
			return '';
		}

		return $id;
	}

	/**
	 * Load immediately if user already chose, or banner is not required (e.g. non-EU).
	 */
	private function shouldLoadNow(): bool {
		/** @var ConsentManager $manager */
		$manager = $this->container->get( 'consent_manager' );
		/** @var GeoLocation $geo */
		$geo = $this->container->get( 'geo_location' );

		if ( $manager->hasStoredConsent() ) {
			return true;
		}

		// No banner needed → safe to load with Consent Mode defaults already in head.
		return ! $geo->shouldShowBanner();
	}

	/**
	 * Standard GTM bootstrap + noscript note via body open optional later.
	 *
	 * @param string $id Container ID.
	 */
	private function printContainerSnippet( string $id ): void {
		$json_id = wp_json_encode( $id );
		if ( false === $json_id ) {
			return;
		}

		$script = sprintf(
			'window.consentaroGTM={id:%1$s,loaded:true};' .
			'(function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({\'gtm.start\':new Date().getTime(),event:\'gtm.js\'});' .
			'var f=d.getElementsByTagName(s)[0],j=d.createElement(s),dl=l!=\'dataLayer\'?\'&l=\'+l:\'\';' .
			'j.async=true;j.src=\'https://www.googletagmanager.com/gtm.js?id=\'+i+dl;' .
			'f.parentNode.insertBefore(j,f);' .
			'})(window,document,\'script\',\'dataLayer\',%1$s);',
			$json_id
		);

		wp_print_inline_script_tag(
			$script,
			array(
				'data-cf-gtm' => 'loaded',
			)
		);

		add_action(
			'wp_body_open',
			static function () use ( $id ): void {
				printf(
					'<noscript><iframe src="https://www.googletagmanager.com/ns.html?id=%s" height="0" width="0" style="display:none;visibility:hidden"></iframe></noscript>' . "\n",
					esc_attr( $id )
				);
			},
			1
		);
	}

	/**
	 * Plugin enabled check.
	 */
	private function isPluginEnabled(): bool {
		/** @var Settings $settings */
		$settings = $this->container->get( 'settings' );
		$data     = $settings->getSettings();
		return ! empty( $data['enabled'] );
	}
}
