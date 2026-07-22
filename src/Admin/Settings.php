<?php
/**
 * Admin settings page (React mount).
 *
 * @package ConsentFlow
 */

declare(strict_types=1);

namespace ConsentFlow\Admin;

use ConsentFlow\Core\ServiceContainer;

/**
 * Registers ConsentFlow menu and enqueues React admin.
 */
final class Settings {

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
	 * Hook registration.
	 */
	public function register(): void {
		add_action( 'admin_menu', array( $this, 'registerMenu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueueAdmin' ) );
	}

	/**
	 * Top-level menu.
	 */
	public function registerMenu(): void {
		add_menu_page(
			'ConsentFlow',
			'ConsentFlow',
			'manage_options',
			'consentflow',
			array( $this, 'renderPage' ),
			'dashicons-privacy',
			30
		);
	}

	/**
	 * React mount point.
	 */
	public function renderPage(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		echo '<div class="wrap"><div id="consentflow-admin"></div></div>';
	}

	/**
	 * Load built admin assets only on our screen.
	 *
	 * @param string $hook Current admin hook.
	 */
	public function enqueueAdmin( string $hook ): void {
		if ( 'toplevel_page_consentflow' !== $hook ) {
			return;
		}

		$asset_file = CONSENTFLOW_PATH . 'assets/build/admin.asset.php';
		$asset      = is_readable( $asset_file )
			? include $asset_file
			: array(
				'dependencies' => array( 'wp-element', 'wp-components', 'wp-api-fetch', 'wp-i18n' ),
				'version'      => CONSENTFLOW_VERSION,
			);

		wp_enqueue_style( 'wp-components' );

		$css_candidates = array(
			'assets/build/style-admin.css',
			'assets/build/admin.css',
			'assets/js/style-admin.css',
		);
		foreach ( $css_candidates as $rel ) {
			if ( is_readable( CONSENTFLOW_PATH . $rel ) ) {
				wp_enqueue_style(
					'consentflow-admin',
					CONSENTFLOW_URL . $rel,
					array( 'wp-components' ),
					(string) ( $asset['version'] ?? CONSENTFLOW_VERSION )
				);
				break;
			}
		}

		wp_enqueue_script(
			'consentflow-admin',
			CONSENTFLOW_URL . 'assets/build/admin.js',
			$asset['dependencies'] ?? array(),
			(string) ( $asset['version'] ?? CONSENTFLOW_VERSION ),
			true
		);

		wp_localize_script(
			'consentflow-admin',
			'consentflowAdmin',
			array(
				'root'  => esc_url_raw( rest_url( 'consentflow/v1' ) ),
				'nonce' => wp_create_nonce( 'wp_rest' ),
			)
		);
	}

	/**
	 * Normalized settings array.
	 *
	 * @return array<string, mixed>
	 */
	public function getSettings(): array {
		$defaults = array(
			'enabled'     => true,
			'gtm_id'      => '',
			'geo_enabled' => true,
			'banner'      => array(
				'position'         => 'bottom',
				'text'             => '',
				'bg'               => '#ffffff',
				'text_color'       => '#1a1a1a',
				'btn_primary_bg'   => '#0073aa',
				'btn_primary_text' => '#ffffff',
			),
		);

		$stored = get_option( 'consentflow_settings', array() );
		if ( ! is_array( $stored ) ) {
			$stored = array();
		}

		$merged           = array_merge( $defaults, $stored );
		$merged['banner'] = array_merge( $defaults['banner'], is_array( $stored['banner'] ?? null ) ? $stored['banner'] : array() );

		return $merged;
	}

	/**
	 * Persist settings.
	 *
	 * @param array<string, mixed> $data Incoming data.
	 * @return array<string, mixed>
	 */
	public function saveSettings( array $data ): array {
		$current = $this->getSettings();

		if ( isset( $data['enabled'] ) ) {
			$current['enabled'] = (bool) $data['enabled'];
		}
		if ( isset( $data['geo_enabled'] ) ) {
			$current['geo_enabled'] = (bool) $data['geo_enabled'];
		}
		if ( isset( $data['gtm_id'] ) ) {
			$gtm = strtoupper( sanitize_text_field( (string) $data['gtm_id'] ) );
			if ( '' === $gtm || preg_match( '/^GTM-[A-Z0-9]+$/', $gtm ) ) {
				$current['gtm_id'] = $gtm;
			}
		}
		if ( isset( $data['banner'] ) && is_array( $data['banner'] ) ) {
			$banner = $data['banner'];
			if ( isset( $banner['position'] ) ) {
				$pos = sanitize_key( (string) $banner['position'] );
				if ( in_array( $pos, array( 'bottom', 'top', 'bottom-right', 'modal' ), true ) ) {
					$current['banner']['position'] = $pos;
				}
			}
			if ( isset( $banner['text'] ) ) {
				$current['banner']['text'] = sanitize_text_field( (string) $banner['text'] );
			}
			foreach ( array( 'bg', 'text_color', 'btn_primary_bg', 'btn_primary_text' ) as $color_key ) {
				if ( isset( $banner[ $color_key ] ) ) {
					$hex = sanitize_hex_color( (string) $banner[ $color_key ] );
					if ( $hex ) {
						$current['banner'][ $color_key ] = $hex;
					}
				}
			}
		}

		update_option( 'consentflow_settings', $current, true );

		/**
		 * Fires after settings are saved.
		 *
		 * @param array $new New settings.
		 * @param array $old Previous (same shape; use carefully).
		 */
		do_action( 'consentflow_settings_updated', $current, $current );

		return $current;
	}
}
