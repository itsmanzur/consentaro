<?php
/**
 * Best-effort settings import from Complianz.
 *
 * @package Consentaro
 */

declare(strict_types=1);

namespace Consentaro\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Consentaro\Core\ServiceContainer;

/**
 * Detects Complianz / CookieYes and, for Complianz only, maps its banner
 * text/position/colors into Consentaro's own settings shape. Never writes
 * anything on its own — previewImport() only reads, applyImport() only
 * runs on an explicit site-owner action via REST.
 *
 * CookieYes is detected but not supported: its free/lite version stores
 * banner text and colors only inside a pre-rendered HTML template string
 * (cky_banner_template), not as structured option values, and does not
 * expose banner position as a plain field at all. Scraping that HTML would
 * be fragile and silently wrong the moment CookieYes changes its markup, so
 * it is intentionally left out of scope rather than guessed at.
 */
final class Import {

	public const SOURCE_COMPLIANZ = 'complianz';
	public const SOURCE_COOKIEYES = 'cookieyes';

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
	 * Which supported plugins are present on this site, active or not,
	 * plus whether Consentaro can actually import from each one.
	 *
	 * @return array<int, array{source: string, name: string, active: bool, import_supported: bool}>
	 */
	public function detectSources(): array {
		$sources = array();

		if ( $this->isComplianzPresent() ) {
			$sources[] = array(
				'source'           => self::SOURCE_COMPLIANZ,
				'name'             => 'Complianz',
				'active'           => $this->isPluginActive( 'complianz-gdpr/complianz-gpdr.php' ),
				'import_supported' => true,
			);
		}

		if ( $this->isCookieYesPresent() ) {
			$sources[] = array(
				'source'           => self::SOURCE_COOKIEYES,
				'name'             => 'CookieYes',
				'active'           => $this->isPluginActive( 'cookie-law-info/cookie-law-info.php' ),
				'import_supported' => false,
			);
		}

		return $sources;
	}

	/**
	 * Read-only mapping preview. Never saves anything.
	 *
	 * @param string $source One of the SOURCE_* constants.
	 * @return array{source: string, supported: bool, banner?: array<string, mixed>, notes?: array<int, string>, error?: string}
	 */
	public function previewImport( string $source ): array {
		if ( self::SOURCE_COMPLIANZ !== $source ) {
			return array(
				'source'    => $source,
				'supported' => false,
				'error'     => 'Import from this plugin is not supported yet.',
			);
		}

		if ( ! $this->isComplianzPresent() ) {
			return array(
				'source'    => $source,
				'supported' => true,
				'error'     => 'Complianz data was not found on this site.',
			);
		}

		$mapped = $this->mapComplianz();
		if ( null === $mapped ) {
			return array(
				'source'    => $source,
				'supported' => true,
				'error'     => 'Could not read Complianz banner settings.',
			);
		}

		return array(
			'source'    => $source,
			'supported' => true,
			'banner'    => $mapped['banner'],
			'notes'     => $mapped['notes'],
		);
	}

	/**
	 * Applies a previously-previewed mapping to Consentaro's real settings.
	 * Only ever called from REST on an explicit "Apply" click — nothing in
	 * this class runs on its own (no hooks, no activation triggers).
	 *
	 * @param string $source One of the SOURCE_* constants.
	 * @return bool True on success.
	 */
	public function applyImport( string $source ): bool {
		if ( self::SOURCE_COMPLIANZ !== $source ) {
			return false;
		}

		$mapped = $this->mapComplianz();
		if ( null === $mapped ) {
			return false;
		}

		/** @var Settings $settings */
		$settings = $this->container->get( 'settings' );
		$settings->saveSettings( array( 'banner' => $mapped['banner'] ) );

		return true;
	}

	/**
	 * Reads Complianz's real cookie-banner object (its own public API,
	 * cmplz_get_cookiebanner()) and maps it into Consentaro's banner shape.
	 * Field-by-field mapping is based on live-inspected Complianz 7.5.x
	 * output, not documentation:
	 *
	 * - position: Complianz has center/bottom/bottom-left/bottom-right.
	 *   Consentaro only has bottom/top/bottom-right/modal, so:
	 *     bottom-right -> bottom-right (exact match)
	 *     bottom       -> bottom
	 *     bottom-left  -> bottom (closest; Consentaro has no left variant)
	 *     center       -> modal (closest equivalent of a centered overlay)
	 * - message_optin (plain string)              -> banner.text
	 * - colorpalette_background['color']           -> banner.bg
	 * - colorpalette_text['color']                 -> banner.text_color
	 * - colorpalette_button_accept['background']   -> banner.btn_primary_bg
	 * - colorpalette_button_accept['text']         -> banner.btn_primary_text
	 * - colorpalette_button_deny['background']     -> banner.btn_secondary_bg
	 * - colorpalette_button_deny['text']           -> banner.btn_secondary_text
	 * - colorpalette_border_radius['top'] (string) -> banner.border_radius
	 * - buttons_border_radius['top'] (string)      -> banner.btn_border_radius
	 * - font_size (int)                            -> banner.font_size
	 *
	 * Everything returned here is still run through Settings::saveSettings()
	 * sanitization (hex color / enum / range checks) before it is ever
	 * persisted, so a malformed Complianz value is rejected rather than
	 * silently stored.
	 *
	 * @return array{banner: array<string, mixed>, notes: array<int, string>}|null
	 */
	private function mapComplianz(): ?array {
		if ( ! function_exists( 'cmplz_get_cookiebanner' ) || ! function_exists( 'cmplz_get_cookiebanners' ) ) {
			return null;
		}

		// cmplz_get_cookiebanner( 'default' ) is NOT "the active banner" — its
		// own loader does (int) $ID > 0 to decide whether to read the DB row
		// at all, and (int) 'default' is 0, so passing the literal string
		// 'default' silently returns Complianz's hardcoded field defaults
		// instead of what the site owner actually configured (confirmed by
		// writing a distinct test value directly to the DB row and seeing
		// cmplz_get_cookiebanner('default') NOT reflect it). The real active
		// banner's numeric ID has to be looked up first.
		$default_rows = cmplz_get_cookiebanners( array( 'default' => true ) );
		if ( empty( $default_rows ) || ! isset( $default_rows[0]->ID ) ) {
			return null;
		}

		$banner = cmplz_get_cookiebanner( (int) $default_rows[0]->ID );
		if ( ! is_object( $banner ) ) {
			return null;
		}

		$position_map = array(
			'bottom-right' => 'bottom-right',
			'bottom'       => 'bottom',
			'bottom-left'  => 'bottom',
			'center'       => 'modal',
		);

		$notes    = array();
		$position = (string) ( $banner->position ?? 'bottom' );
		if ( isset( $position_map[ $position ] ) ) {
			if ( 'bottom-left' === $position ) {
				$notes[] = 'Complianz position "bottom-left" has no equivalent in Consentaro — mapped to "bottom".';
			} elseif ( 'center' === $position ) {
				$notes[] = 'Complianz position "center" was mapped to Consentaro\'s "modal" layout.';
			}
			$position = $position_map[ $position ];
		} else {
			$notes[]  = 'Unrecognized Complianz position "' . $position . '" — defaulted to "bottom".';
			$position = 'bottom';
		}

		$bg_color       = $banner->colorpalette_background ?? array();
		$text_color     = $banner->colorpalette_text ?? array();
		$accept_colors  = $banner->colorpalette_button_accept ?? array();
		$deny_colors    = $banner->colorpalette_button_deny ?? array();
		$border_radius  = $banner->colorpalette_border_radius ?? array();
		$btn_radius     = $banner->buttons_border_radius ?? array();

		$mapped_banner = array(
			'position'           => $position,
			'text'               => (string) ( $banner->message_optin ?? '' ),
			'bg'                 => (string) ( $bg_color['color'] ?? '#ffffff' ),
			'text_color'         => (string) ( $text_color['color'] ?? '#1a1a1a' ),
			'btn_primary_bg'     => (string) ( $accept_colors['background'] ?? '#0E7C66' ),
			'btn_primary_text'   => (string) ( $accept_colors['text'] ?? '#ffffff' ),
			'btn_secondary_bg'   => (string) ( $deny_colors['background'] ?? '#f0f0f1' ),
			'btn_secondary_text' => (string) ( $deny_colors['text'] ?? '#1a1a1a' ),
			'border_radius'      => (int) ( $border_radius['top'] ?? 8 ),
			'btn_border_radius'  => (int) ( $btn_radius['top'] ?? 4 ),
			'font_size'          => (int) ( $banner->font_size ?? 13 ),
		);

		return array(
			'banner' => $mapped_banner,
			'notes'  => $notes,
		);
	}

	/**
	 * Whether Complianz's cookie-banner data is readable right now (plugin
	 * active with its functions loaded — files-only-present isn't enough
	 * since we need the live PHP API, not the DB table directly).
	 */
	private function isComplianzPresent(): bool {
		return $this->isPluginActive( 'complianz-gdpr/complianz-gpdr.php' )
			&& function_exists( 'cmplz_get_cookiebanner' )
			&& function_exists( 'cmplz_get_cookiebanners' );
	}

	/**
	 * Whether CookieYes is active on this site.
	 */
	private function isCookieYesPresent(): bool {
		return $this->isPluginActive( 'cookie-law-info/cookie-law-info.php' )
			|| false !== get_option( 'cky_settings', false );
	}

	/**
	 * is_plugin_active() wrapper — that function lives in an admin-only file
	 * that isn't always loaded (e.g. during a REST request).
	 *
	 * @param string $plugin_file Relative plugin file, e.g. "foo/foo.php".
	 */
	private function isPluginActive( string $plugin_file ): bool {
		if ( ! function_exists( 'is_plugin_active' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		return is_plugin_active( $plugin_file );
	}
}
