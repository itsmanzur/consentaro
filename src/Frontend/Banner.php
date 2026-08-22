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

		$text = (string) ( $banner['text'] ?? '' );
		if ( '' === $text ) {
			$text = __( 'We use cookies to improve your experience and measure traffic.', 'consentaro' );
		}

		$style = $this->getCustomStyles( $banner );
		$html  = $this->buildHtml( $position, $text, $style );

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
	 */
	private function buildHtml( string $position, string $text, string $style ): string {
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

		// A true modal (position=modal) covers the whole page and warrants
		// role="dialog" + aria-modal. bottom/top/bottom-right are a persistent
		// but non-blocking region — marking those aria-modal would wrongly
		// tell screen readers the rest of the page is inaccessible.
		$is_modal_position = 'modal' === $position;
		$root_role         = $is_modal_position ? 'dialog' : 'region';
		$root_aria_modal   = $is_modal_position ? ' aria-modal="true"' : '';

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
			'</div>' .
			'<div id="consentaro-banner-modal" class="consentaro-banner__modal" role="dialog" aria-modal="true" aria-labelledby="consentaro-banner-modal-title">' .
				'<div class="consentaro-banner__modal-card">' .
					'<h2 id="consentaro-banner-modal-title">%9$s</h2>' .
					'<ul class="consentaro-banner__list">%10$s</ul>' .
					'<div class="consentaro-banner__modal-actions">' .
						'<button type="button" class="consentaro-banner__btn consentaro-banner__btn--ghost" data-consentaro-close>%11$s</button>' .
						'<button type="button" class="consentaro-banner__btn consentaro-banner__btn--primary" data-consentaro-save-custom>%12$s</button>' .
					'</div>' .
				'</div>' .
			'</div>',
			esc_attr( $position ),
			esc_attr( $style ),
			esc_attr( $root_role ),
			$root_aria_modal,
			esc_html( $text ),
			esc_html__( 'Accept All', 'consentaro' ),
			esc_html__( 'Deny All', 'consentaro' ),
			esc_html__( 'Customize', 'consentaro' ),
			esc_html__( 'Customize cookies', 'consentaro' ),
			$items,
			esc_html__( 'Cancel', 'consentaro' ),
			esc_html__( 'Save preferences', 'consentaro' )
		);
	}
}
