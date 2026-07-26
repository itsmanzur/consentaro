<?php
/**
 * Main plugin bootstrapper.
 *
 * @package Consentaro
 */

declare(strict_types=1);

namespace Consentaro\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Consentaro\Admin\REST_Controller;
use Consentaro\Admin\Settings;
use Consentaro\Compatibility\CachePlugins;
use Consentaro\Consent\ConsentManager;
use Consentaro\Consent\ConsentMode;
use Consentaro\Consent\CookieStorage;
use Consentaro\Frontend\Assets;
use Consentaro\Frontend\Banner;
use Consentaro\Integration\GeoLocation;
use Consentaro\Integration\GTM;
use Consentaro\Integration\WooCommerce;

/**
 * Singleton plugin orchestrator.
 */
final class Plugin {

	/**
	 * Instance.
	 *
	 * @var self|null
	 */
	private static ?self $instance = null;

	/**
	 * Service container.
	 *
	 * @var ServiceContainer
	 */
	private ServiceContainer $container;

	/**
	 * Get singleton.
	 */
	public static function get_instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Constructor.
	 */
	private function __construct() {
		$this->container = new ServiceContainer();
	}

	/**
	 * Boot hooks and services.
	 */
	public function init(): void {
		$this->register_services();
		$this->load_modules();
	}

	/**
	 * Register DI services.
	 */
	private function register_services(): void {
		$c = $this->container;

		$c->set(
			'cookie_storage',
			static function (): CookieStorage {
				return new CookieStorage();
			}
		);

		$c->set(
			'consent_mode',
			static function (): ConsentMode {
				return new ConsentMode();
			}
		);

		$c->set(
			'consent_manager',
			static function ( ServiceContainer $container ): ConsentManager {
				return new ConsentManager(
					$container->get( 'cookie_storage' ),
					$container->get( 'consent_mode' )
				);
			}
		);

		$c->set(
			'geo_location',
			static function (): GeoLocation {
				return new GeoLocation();
			}
		);

		$c->set(
			'settings',
			static function ( ServiceContainer $container ): Settings {
				return new Settings( $container );
			}
		);

		$c->set(
			'rest_controller',
			static function ( ServiceContainer $container ): REST_Controller {
				return new REST_Controller( $container );
			}
		);

		$c->set(
			'assets',
			static function ( ServiceContainer $container ): Assets {
				return new Assets( $container );
			}
		);

		$c->set(
			'banner',
			static function ( ServiceContainer $container ): Banner {
				return new Banner( $container );
			}
		);

		$c->set(
			'gtm',
			static function ( ServiceContainer $container ): GTM {
				return new GTM( $container );
			}
		);

		$c->set(
			'cache_plugins',
			static function ( ServiceContainer $container ): CachePlugins {
				return new CachePlugins( $container );
			}
		);
	}

	/**
	 * Wire modules / hooks.
	 */
	private function load_modules(): void {
		$this->container->get( 'consent_manager' )->register();
		$this->container->get( 'settings' )->register();
		$this->container->get( 'rest_controller' )->register();
		$this->container->get( 'assets' )->register();
		$this->container->get( 'banner' )->register();
		$this->container->get( 'gtm' )->register();
		$this->container->get( 'cache_plugins' )->register();

		// WooCommerce may load after us on plugins_loaded.
		add_action( 'woocommerce_loaded', array( $this, 'bootWooCommerce' ) );
		// Fallback if WC already active.
		if ( class_exists( '\WooCommerce' ) ) {
			$this->bootWooCommerce();
		}
	}

	/**
	 * Register WooCommerce integration once.
	 */
	public function bootWooCommerce(): void {
		if ( $this->container->has( 'woocommerce' ) ) {
			return;
		}

		$this->container->set(
			'woocommerce',
			function ( ServiceContainer $container ): WooCommerce {
				return new WooCommerce( $container );
			}
		);

		$this->container->get( 'woocommerce' )->register();
	}

	/**
	 * Expose container (tests / extensions).
	 */
	public function container(): ServiceContainer {
		return $this->container;
	}
}
