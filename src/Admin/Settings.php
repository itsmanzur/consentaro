<?php
/**
 * Admin settings page (React mount).
 *
 * @package Consentaro
 */

declare(strict_types=1);

namespace Consentaro\Admin;

use Consentaro\Core\ServiceContainer;

/**
 * Registers Consentaro menu and enqueues React admin.
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
		add_action( 'admin_init', array( $this, 'maybeRedirectAfterActivation' ) );
	}

	/**
	 * One-time redirect to the Guide screen right after activation, so a new
	 * admin lands on the walkthrough instead of having to find the menu.
	 */
	public function maybeRedirectAfterActivation(): void {
		if ( ! get_transient( 'consentaro_activation_redirect' ) ) {
			return;
		}

		delete_transient( 'consentaro_activation_redirect' );

		if ( wp_doing_ajax() || ! current_user_can( 'manage_options' ) ) {
			return;
		}

		wp_safe_redirect( admin_url( 'admin.php?page=consentaro' ) );
		exit;
	}

	/**
	 * Top-level menu.
	 */
	public function registerMenu(): void {
		add_menu_page(
			'Consentaro',
			'Consentaro',
			'manage_options',
			'consentaro',
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

		echo '<div class="wrap"><div id="consentaro-admin"></div></div>';
	}

	/**
	 * Load built admin assets only on our screen.
	 *
	 * @param string $hook Current admin hook.
	 */
	public function enqueueAdmin( string $hook ): void {
		if ( 'toplevel_page_consentaro' !== $hook ) {
			return;
		}

		$asset_file = CONSENTARO_PATH . 'assets/build/admin.asset.php';
		$asset      = is_readable( $asset_file )
			? include $asset_file
			: array(
				'dependencies' => array( 'wp-element', 'wp-components', 'wp-api-fetch', 'wp-i18n' ),
				'version'      => CONSENTARO_VERSION,
			);

		wp_enqueue_style( 'wp-components' );

		$css_candidates = array(
			'assets/build/style-admin.css',
			'assets/build/admin.css',
			'assets/js/style-admin.css',
		);
		foreach ( $css_candidates as $rel ) {
			if ( is_readable( CONSENTARO_PATH . $rel ) ) {
				wp_enqueue_style(
					'consentaro-admin',
					CONSENTARO_URL . $rel,
					array( 'wp-components' ),
					(string) ( $asset['version'] ?? CONSENTARO_VERSION )
				);
				break;
			}
		}

		wp_enqueue_script(
			'consentaro-admin',
			CONSENTARO_URL . 'assets/build/admin.js',
			$asset['dependencies'] ?? array(),
			(string) ( $asset['version'] ?? CONSENTARO_VERSION ),
			true
		);

		wp_localize_script(
			'consentaro-admin',
			'consentaroAdmin',
			array(
				'root'  => esc_url_raw( rest_url( 'consentaro/v1' ) ),
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

		$stored = get_option( 'consentaro_settings', array() );
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
		$current  = $this->getSettings();
		$rejected = array();

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
			} else {
				$rejected[] = 'gtm_id';
			}
		}
		if ( isset( $data['banner'] ) && is_array( $data['banner'] ) ) {
			$banner = $data['banner'];
			if ( isset( $banner['position'] ) ) {
				$pos = sanitize_key( (string) $banner['position'] );
				if ( in_array( $pos, array( 'bottom', 'top', 'bottom-right', 'modal' ), true ) ) {
					$current['banner']['position'] = $pos;
				} else {
					$rejected[] = 'banner.position';
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
					} else {
						$rejected[] = 'banner.' . $color_key;
					}
				}
			}
		}

		update_option( 'consentaro_settings', $current, true );

		/**
		 * Fires after settings are saved.
		 *
		 * @param array $new New settings.
		 * @param array $old Previous (same shape; use carefully).
		 */
		do_action( 'consentaro_settings_updated', $current, $current );

		// Not persisted — surfaced to the REST response only, so the admin
		// UI can tell the difference between "saved" and "silently ignored".
		$current['rejected'] = $rejected;

		return $current;
	}
}
