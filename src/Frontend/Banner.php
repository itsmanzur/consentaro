<?php
/**
 * Consent banner renderer.
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
use Consentaro\Consent\ConsentManager;
use Consentaro\Integration\GeoLocation;

/**
 * Outputs banner HTML when geo + consent rules match.
 */
final class Banner {

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
		add_action( 'wp_footer', array( $this, 'render' ), 5 );
		add_action( 'wp_footer', array( $this, 'renderPreferencesModal' ), 6 );
		add_shortcode( 'consentaro_settings', array( $this, 'settingsLink' ) );
	}

	/**
	 * Set when a [consentaro_settings] link is on the page, so the preferences
	 * modal gets printed even though the banner itself is not.
	 *
	 * @var bool
	 */
	private bool $preferencesRequested = false;

	/**
	 * [consentaro_settings label="Cookie settings"] — a button that lets visitors
	 * change their cookie choice later. Any element with
	 * data-consentaro-action="reopen" does the same once Consentaro's script is on the page.
	 *
	 * @param array<string, string>|string $atts Shortcode attributes.
	 */
	public function settingsLink( $atts = array() ): string {
		$atts = shortcode_atts(
			array(
				'label' => __( 'Cookie settings', 'consentaro' ),
				'class' => '',
			),
			is_array( $atts ) ? $atts : array(),
			'consentaro_settings'
		);

		$settings = get_option( 'consentaro_settings', array() );
		if ( is_array( $settings ) && isset( $settings['enabled'] ) && empty( $settings['enabled'] ) ) {
			return '';
		}

		/** @var GeoLocation $geo */
		$geo = $this->container->get( 'geo_location' );
		// Show the link when the banner applies to this visitor, or when they have already decided.
		if ( ! $geo->shouldShowBanner() && empty( $_COOKIE[ ConsentManager::COOKIE_NAME ] ) ) {
			return '';
		}

		$this->preferencesRequested = true;
		/** @var Assets $assets */
		$assets = $this->container->get( 'assets' );
		$assets->enqueueBanner();

		$class = trim( 'consentaro-settings-link ' . sanitize_html_class( (string) $atts['class'] ) );

		return sprintf(
			'<button type="button" class="%1$s" data-consentaro-action="reopen">%2$s</button>',
			esc_attr( $class ),
			esc_html( (string) $atts['label'] )
		);
	}

	/**
	 * Print only the preferences modal when the banner is not shown (the visitor has already
	 * decided) but a settings link is on the page.
	 */
	public function renderPreferencesModal(): void {
		if ( ! $this->preferencesRequested ) {
			return;
		}

		/** @var GeoLocation $geo */
		$geo = $this->container->get( 'geo_location' );
		if ( $geo->shouldShowBanner() ) {
			return; // render() already printed the banner and the modal.
		}

		/** @var Settings $settings_svc */
		$settings_svc = $this->container->get( 'settings' );
		$settings     = $settings_svc->getSettings();
		$banner       = is_array( $settings['banner'] ?? null ) ? $settings['banner'] : array();

		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Built with escaping helpers.
		echo $this->buildModalHtml( $this->getCustomStyles( $banner ) );
	}

	/**
	 * Render banner + customize modal.
	 */
	public function render(): void {
		/** @var GeoLocation $geo */
		$geo = $this->container->get( 'geo_location' );
		if ( ! $geo->shouldShowBanner() ) {
			return;
		}

		/** @var Settings $settings_svc */
		$settings_svc = $this->container->get( 'settings' );
		$settings     = $settings_svc->getSettings();
		$banner       = is_array( $settings['banner'] ?? null ) ? $settings['banner'] : array();

		$position = sanitize_key( (string) ( $banner['position'] ?? 'bottom' ) );
		if ( ! in_array( $position, array( 'bottom', 'top', 'bottom-right', 'modal' ), true ) ) {
			$position = 'bottom';
		}

		$text = $this->translateBannerText( (string) ( $banner['text'] ?? '' ) );
		if ( '' === $text ) {
			$text = __( 'We use cookies to improve your experience and measure traffic.', 'consentaro' );
		}

		$labels = array(
			'accept'    => $this->translateString( (string) ( $banner['label_accept'] ?? '' ), Settings::defaultLabel( 'accept' ), 'Accept Button' ),
			'deny'      => $this->translateString( (string) ( $banner['label_deny'] ?? '' ), Settings::defaultLabel( 'deny' ), 'Deny Button' ),
			'customize' => $this->translateString( (string) ( $banner['label_customize'] ?? '' ), Settings::defaultLabel( 'customize' ), 'Customize Button' ),
		);

		$style = $this->getCustomStyles( $banner );
		$html  = $this->buildHtml( $position, $text, $style, $labels, $this->privacyUrl( $banner ) );

		/**
		 * Filter banner HTML before output.
		 *
		 * @param string               $html     Markup.
		 * @param array<string, mixed> $settings Full settings.
		 */
		$html = (string) apply_filters( 'consentaro_banner_html', $html, $settings );

		do_action( 'consentaro_before_banner_display' );

		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Built with escaping helpers.
		echo $html;

		do_action( 'consentaro_after_banner_display' );
	}

	/**
	 * Resolves banner.text through WPML / Polylang string translation when
	 * either plugin is active, so a multilingual site shows the per-language
	 * version a site owner set under Languages → String Translations. A
	 * no-op on an ordinary single-language site — has_filter()/
	 * function_exists() are simply false, and $text passes through unchanged.
	 *
	 * @param string $text Raw banner.text setting value.
	 */
	private function translateBannerText( string $text ): string {
		if ( has_filter( 'wpml_translate_single_string' ) ) {
			$text = (string) apply_filters( 'wpml_translate_single_string', $text, 'Consentaro', 'Banner Text' );
		} elseif ( function_exists( 'pll__' ) ) {
			$text = pll__( $text );
		}

		return $text;
	}

	/**
	 * Same WPML / Polylang resolution for a button label. A blank or
	 * default-valued label falls straight through to the built-in string,
	 * which WordPress already translates.
	 *
	 * @param string $value    Stored label.
	 * @param string $fallback Translated built-in default.
	 * @param string $name     WPML string name.
	 */
	private function translateString( string $value, string $fallback, string $name ): string {
		$value = trim( $value );
		if ( '' === $value || $value === $fallback ) {
			return $fallback;
		}

		if ( has_filter( 'wpml_translate_single_string' ) ) {
			$value = (string) apply_filters( 'wpml_translate_single_string', $value, 'Consentaro', $name );
		} elseif ( function_exists( 'pll__' ) ) {
			$value = pll__( $value );
		}

		return $value;
	}

	/**
	 * Resolved privacy-policy URL: a chosen page wins over a custom URL.
	 * Empty string when neither is set (or the page is no longer published).
	 *
	 * @param array<string, mixed> $banner Banner settings.
	 */
	private function privacyUrl( array $banner ): string {
		$page_id = (int) ( $banner['privacy_page_id'] ?? 0 );
		if ( $page_id > 0 && 'publish' === get_post_status( $page_id ) ) {
			$permalink = get_permalink( $page_id );
			if ( is_string( $permalink ) && '' !== $permalink ) {
				return $permalink;
			}
		}

		return (string) ( $banner['privacy_url'] ?? '' );
	}

	/**
	 * Inline CSS variables from settings.
	 *
	 * @param array<string, mixed> $banner Banner settings.
	 */
	public function getCustomStyles( array $banner ): string {
		$border_radius     = isset( $banner['border_radius'] ) ? absint( $banner['border_radius'] ) : 8;
		$btn_border_radius = isset( $banner['btn_border_radius'] ) ? absint( $banner['btn_border_radius'] ) : 4;
		$font_size         = isset( $banner['font_size'] ) ? absint( $banner['font_size'] ) : 13;

		$layout    = sanitize_key( (string) ( $banner['btn_layout'] ?? 'inline' ) );
		$direction = 'stacked' === $layout ? 'column' : 'row';

		$align       = sanitize_key( (string) ( $banner['btn_align'] ?? 'left' ) );
		$justify_map = array(
			'left'   => 'flex-start',
			'center' => 'center',
			'right'  => 'flex-end',
		);
		$justify = $justify_map[ $align ] ?? 'flex-start';

		// Positions the button group itself within the banner row/column when
		// it isn't already stretched full-width (modal/bottom-right handle
		// that via --consentaro-btn-justify + align-items on a stretched box).
		$margin_map = array(
			'left'   => '0',
			'center' => '0 auto',
			'right'  => '0 0 0 auto',
		);
		$margin = $margin_map[ $align ] ?? '0';

		$map = array(
			'--consentaro-bg'                 => sanitize_hex_color( (string) ( $banner['bg'] ?? '' ) ) ?: '#ffffff',
			'--consentaro-text'                => sanitize_hex_color( (string) ( $banner['text_color'] ?? '' ) ) ?: '#1a1a1a',
			'--consentaro-btn-bg'              => sanitize_hex_color( (string) ( $banner['btn_primary_bg'] ?? '' ) ) ?: '#0E7C66',
			'--consentaro-btn-text'            => sanitize_hex_color( (string) ( $banner['btn_primary_text'] ?? '' ) ) ?: '#ffffff',
			'--consentaro-btn-secondary-bg'    => sanitize_hex_color( (string) ( $banner['btn_secondary_bg'] ?? '' ) ) ?: '#f0f0f1',
			'--consentaro-btn-secondary-text'  => sanitize_hex_color( (string) ( $banner['btn_secondary_text'] ?? '' ) ) ?: '#1a1a1a',
			'--consentaro-radius'              => $border_radius . 'px',
			'--consentaro-btn-radius'          => $btn_border_radius . 'px',
			'--consentaro-btn-direction'       => $direction,
			'--consentaro-btn-justify'         => $justify,
			'--consentaro-btn-margin'          => $margin,
			'--consentaro-font-size'           => $font_size . 'px',
		);

		$parts = array();
		foreach ( $map as $prop => $value ) {
			$parts[] = $prop . ':' . $value;
		}

		return implode( ';', $parts );
	}

	/**
	 * Whether banner should display (geo helper).
	 */
	public function shouldDisplay(): bool {
		/** @var GeoLocation $geo */
		$geo = $this->container->get( 'geo_location' );
		return $geo->shouldShowBanner();
	}

	/**
	 * Build escaped markup.
	 *
	 * @param string $position Position slug.
	 * @param string $text     Banner copy.
	 * @param string $style    Inline CSS vars.
	 * @param array<string, string> $labels      Button labels (accept/deny/customize).
	 * @param string                $privacy_url Privacy policy URL, or ''.
	 */
	private function buildHtml( string $position, string $text, string $style, array $labels, string $privacy_url = '' ): string {
		// A true modal (position=modal) covers the whole page and warrants
		// role="dialog" + aria-modal. bottom/top/bottom-right are a persistent
		// but non-blocking region — marking those aria-modal would wrongly
		// tell screen readers the rest of the page is inaccessible.
		$is_modal_position = 'modal' === $position;
		$root_role         = $is_modal_position ? 'dialog' : 'region';
		$root_aria_modal   = $is_modal_position ? ' aria-modal="true"' : '';

		$text_html = esc_html( $text );
		if ( '' !== $privacy_url ) {
			$text_html .= sprintf(
				' <a class="consentaro-banner__link" href="%1$s" target="_blank" rel="noopener noreferrer">%2$s</a>',
				esc_url( $privacy_url ),
				esc_html__( 'Privacy Policy', 'consentaro' )
			);
		}

		return sprintf(
			'<div id="consentaro-banner" class="consentaro-banner consentaro-banner--%1$s" style="%2$s" role="%3$s"%4$s aria-labelledby="consentaro-banner-text">' .
				'<div class="consentaro-banner__panel">' .
					'<p class="consentaro-banner__text" id="consentaro-banner-text">%5$s</p>' .
					'<div class="consentaro-banner__buttons">' .
						'<button type="button" class="consentaro-banner__btn consentaro-banner__btn--primary" data-consentaro-action="accept-all">%6$s</button>' .
						'<button type="button" class="consentaro-banner__btn consentaro-banner__btn--ghost" data-consentaro-action="deny-all">%7$s</button>' .
						'<button type="button" class="consentaro-banner__btn consentaro-banner__btn--ghost" data-consentaro-action="customize">%8$s</button>' .
					'</div>' .
				'</div>' .
			'</div>',
			esc_attr( $position ),
			esc_attr( $style ),
			esc_attr( $root_role ),
			$root_aria_modal,
			$text_html,
			esc_html( $labels['accept'] ),
			esc_html( $labels['deny'] ),
			esc_html( $labels['customize'] )
		) . $this->buildModalHtml( $style );
	}

	/**
	 * The "Customize cookies" preferences modal. It sits outside #consentaro-banner, so it
	 * needs the same color variables on its own element.
	 *
	 * @param string $style Inline CSS vars.
	 */
	private function buildModalHtml( string $style ): string {
		$categories = array(
			'analytics_storage'       => __( 'Analytics', 'consentaro' ),
			'ad_storage'              => __( 'Advertising', 'consentaro' ),
			'ad_user_data'            => __( 'Ad user data', 'consentaro' ),
			'ad_personalization'      => __( 'Ad personalization', 'consentaro' ),
			'personalization_storage' => __( 'Personalization', 'consentaro' ),
		);

		$items = '';
		foreach ( $categories as $type => $label ) {
			$items .= sprintf(
				'<li><label for="consentaro-%1$s">%2$s</label><input id="consentaro-%1$s" type="checkbox" data-consentaro-type="%1$s" /></li>',
				esc_attr( $type ),
				esc_html( $label )
			);
		}

		return sprintf(
			'<div id="consentaro-banner-modal" class="consentaro-banner__modal" style="%1$s" role="dialog" aria-modal="true" aria-labelledby="consentaro-banner-modal-title">' .
				'<div class="consentaro-banner__modal-card">' .
					'<h2 id="consentaro-banner-modal-title">%2$s</h2>' .
					'<ul class="consentaro-banner__list">%3$s</ul>' .
					'<div class="consentaro-banner__modal-actions">' .
						'<button type="button" class="consentaro-banner__btn consentaro-banner__btn--ghost" data-consentaro-close>%4$s</button>' .
						'<button type="button" class="consentaro-banner__btn consentaro-banner__btn--primary" data-consentaro-save-custom>%5$s</button>' .
					'</div>' .
				'</div>' .
			'</div>',
			esc_attr( $style ),
			esc_html__( 'Customize cookies', 'consentaro' ),
			$items,
			esc_html__( 'Cancel', 'consentaro' ),
			esc_html__( 'Save preferences', 'consentaro' )
		);
	}
}
