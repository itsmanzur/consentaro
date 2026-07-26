<?php
/**
 * WooCommerce tracking gated by Consent Mode states.
 *
 * @package Consentaro
 */

declare(strict_types=1);

namespace Consentaro\Integration;

use Consentaro\Consent\ConsentManager;
use Consentaro\Core\ServiceContainer;
use WC_Order;
use WC_Product;

/**
 * Pushes Enhanced Ecommerce-style dataLayer events only when consent allows.
 */
final class WooCommerce {

	private const SESSION_ATC = 'consentaro_atc';

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
	 * Register only when WooCommerce is available.
	 */
	public function register(): void {
		if ( ! class_exists( '\WooCommerce' ) ) {
			return;
		}

		add_action( 'wp_enqueue_scripts', array( $this, 'enqueueScripts' ) );
		add_action( 'woocommerce_add_to_cart', array( $this, 'onAddToCart' ), 10, 6 );
		add_action( 'woocommerce_thankyou', array( $this, 'onThankYou' ), 20, 1 );
		add_action( 'wp_footer', array( $this, 'printQueuedEvents' ), 20 );
	}

	/**
	 * Front-end flags + AJAX add-to-cart listener.
	 */
	public function enqueueScripts(): void {
		if ( is_admin() || ! $this->isEnabled() ) {
			return;
		}

		$events = $this->trackedEvents();
		if ( empty( array_filter( $events ) ) ) {
			return;
		}

		$path = CONSENTARO_PATH . 'assets/js/woo.js';
		$ver  = is_readable( $path ) ? (string) filemtime( $path ) : CONSENTARO_VERSION;

		wp_enqueue_script(
			'consentaro-woo',
			CONSENTARO_URL . 'assets/js/woo.js',
			array( 'jquery' ),
			$ver,
			array(
				'in_footer' => true,
				'strategy'  => 'defer',
			)
		);

		/** @var ConsentManager $manager */
		$manager = $this->container->get( 'consent_manager' );

		wp_localize_script(
			'consentaro-woo',
			'consentaroWoo',
			array(
				'analytics' => $manager->hasConsent( 'analytics_storage' ),
				'ads'       => $manager->hasConsent( 'ad_storage' ),
				'events'    => $events,
				'currency'  => function_exists( 'get_woocommerce_currency' ) ? get_woocommerce_currency() : 'USD',
			)
		);
	}

	/**
	 * Queue add_to_cart for non-AJAX flows when analytics allowed.
	 *
	 * @param string $cart_item_key Cart key.
	 * @param int    $product_id    Product ID.
	 * @param int    $quantity      Qty.
	 * @param int    $variation_id  Variation ID.
	 * @param array  $variation     Variation attrs.
	 * @param array  $cart_item_data Extra data.
	 */
	public function onAddToCart( $cart_item_key, $product_id, $quantity, $variation_id = 0, $variation = array(), $cart_item_data = array() ): void {
		unset( $cart_item_key, $variation, $cart_item_data );

		if ( ! $this->shouldTrack( 'add_to_cart' ) ) {
			return;
		}

		// AJAX add-to-cart is handled by woo.js (avoid duplicate dataLayer push).
		if ( wp_doing_ajax() ) {
			return;
		}

		/** @var ConsentManager $manager */
		$manager = $this->container->get( 'consent_manager' );
		if ( ! $manager->hasConsent( 'analytics_storage' ) ) {
			$this->maybeQueueAnonymous( 'add_to_cart', (int) $product_id );
			return;
		}

		$product = wc_get_product( $variation_id ? (int) $variation_id : (int) $product_id );
		if ( ! $product instanceof WC_Product ) {
			return;
		}

		$payload = array(
			'event'     => 'add_to_cart',
			'ecommerce' => array(
				'currency' => get_woocommerce_currency(),
				'value'    => (float) $product->get_price() * max( 1, (int) $quantity ),
				'items'    => array( $this->formatItem( $product, (int) $quantity ) ),
			),
		);

		if ( function_exists( 'WC' ) && WC()->session ) {
			WC()->session->set( self::SESSION_ATC, $payload );
		}
	}

	/**
	 * Purchase event on order received when ads + analytics granted.
	 *
	 * @param int $order_id Order ID.
	 */
	public function onThankYou( $order_id ): void {
		$order_id = (int) $order_id;
		if ( $order_id <= 0 || ! $this->shouldTrack( 'purchase' ) ) {
			return;
		}

		/** @var ConsentManager $manager */
		$manager = $this->container->get( 'consent_manager' );
		if ( ! $manager->hasConsent( 'analytics_storage' ) || ! $manager->hasConsent( 'ad_storage' ) ) {
			$this->maybeQueueAnonymous( 'purchase', $order_id );
			return;
		}

		$order = wc_get_order( $order_id );
		if ( ! $order instanceof WC_Order ) {
			return;
		}

		// Avoid duplicate pushes on thank-you refresh.
		if ( $order->get_meta( '_consentaro_purchase_pushed' ) ) {
			return;
		}

		$items = array();
		foreach ( $order->get_items() as $item ) {
			$product = $item->get_product();
			if ( $product instanceof WC_Product ) {
				$items[] = $this->formatItem( $product, (int) $item->get_quantity() );
			}
		}

		$payload = array(
			'event'     => 'purchase',
			'ecommerce' => array(
				'transaction_id' => $order->get_order_number(),
				'currency'       => $order->get_currency(),
				'value'          => (float) $order->get_total(),
				'tax'            => (float) $order->get_total_tax(),
				'shipping'       => (float) $order->get_shipping_total(),
				'items'          => $items,
			),
		);

		$order->update_meta_data( '_consentaro_purchase_pushed', '1' );
		$order->save();

		$this->queueInlineEvent( $payload );
	}

	/**
	 * Flush session ATC + view_item + any inline queue.
	 */
	public function printQueuedEvents(): void {
		if ( is_admin() || ! $this->isEnabled() ) {
			return;
		}

		$events = array();

		if ( $this->shouldTrack( 'view_item' ) && function_exists( 'is_product' ) && is_product() ) {
			/** @var ConsentManager $manager */
			$manager = $this->container->get( 'consent_manager' );
			if ( $manager->hasConsent( 'analytics_storage' ) ) {
				$product = wc_get_product( get_the_ID() );
				if ( $product instanceof WC_Product ) {
					$events[] = array(
						'event'     => 'view_item',
						'ecommerce' => array(
							'currency' => get_woocommerce_currency(),
							'value'    => (float) $product->get_price(),
							'items'    => array( $this->formatItem( $product, 1 ) ),
						),
					);
				}
			}
		}

		if ( function_exists( 'WC' ) && WC()->session ) {
			$atc = WC()->session->get( self::SESSION_ATC );
			if ( is_array( $atc ) ) {
				$events[] = $atc;
				WC()->session->set( self::SESSION_ATC, null );
			}
		}

		$inline = $this->consumeInlineQueue();
		if ( $inline ) {
			$events = array_merge( $events, $inline );
		}

		if ( empty( $events ) ) {
			return;
		}

		$pushes = '';
		foreach ( $events as $payload ) {
			// HEX flags prevent a product/order field (e.g. an item name)
			// containing "</script>" from breaking out of the inline tag.
			$json = wp_json_encode( $payload, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT );
			if ( false !== $json ) {
				$pushes .= 'window.dataLayer.push(' . $json . ');';
			}
		}

		if ( '' === $pushes ) {
			return;
		}

		wp_print_inline_script_tag(
			'window.dataLayer=window.dataLayer||[];' . $pushes,
			array(
				'data-consentaro-woo' => '1',
			)
		);
	}

	/**
	 * Which events are enabled.
	 *
	 * @return array<string, bool>
	 */
	private function trackedEvents(): array {
		$defaults = array(
			'add_to_cart' => true,
			'purchase'    => true,
			'view_item'   => true,
		);

		/**
		 * Filter WooCommerce events Consentaro may track.
		 *
		 * @param array<string, bool> $events Event map.
		 */
		$filtered = apply_filters( 'consentaro_woocommerce_track_events', $defaults );

		if ( ! is_array( $filtered ) ) {
			return $defaults;
		}

		$out = array();
		foreach ( $defaults as $key => $default ) {
			$out[ $key ] = isset( $filtered[ $key ] ) ? (bool) $filtered[ $key ] : $default;
		}

		return $out;
	}

	/**
	 * @param string $event Event key.
	 */
	private function shouldTrack( string $event ): bool {
		if ( ! $this->isEnabled() ) {
			return false;
		}
		$events = $this->trackedEvents();
		return ! empty( $events[ $event ] );
	}

	/**
	 * GA4 item shape.
	 *
	 * @param WC_Product $product  Product.
	 * @param int        $quantity Qty.
	 * @return array<string, mixed>
	 */
	private function formatItem( WC_Product $product, int $quantity ): array {
		return array(
			'item_id'   => (string) $product->get_id(),
			'item_name' => $product->get_name(),
			'price'     => (float) $product->get_price(),
			'quantity'  => max( 1, $quantity ),
		);
	}

	/**
	 * Optional anonymized fallback (off by default).
	 *
	 * @param string $event Event name.
	 * @param int    $ref   Product or order ID.
	 */
	private function maybeQueueAnonymous( string $event, int $ref ): void {
		/**
		 * Enable anonymized WP-Cron fallback when consent is missing.
		 *
		 * @param bool   $enabled Default false.
		 * @param string $event   Event name.
		 * @param int    $ref     Reference ID.
		 */
		if ( ! apply_filters( 'consentaro_woocommerce_anonymized_fallback', false, $event, $ref ) ) {
			return;
		}

		$queue   = get_option( 'consentaro_woo_anon_queue', array() );
		$queue   = is_array( $queue ) ? $queue : array();
		$queue[] = array(
			'event' => sanitize_key( $event ),
			'ref'   => $ref,
			'ts'    => time(),
		);
		update_option( 'consentaro_woo_anon_queue', array_slice( $queue, -50 ), false );
	}

	/**
	 * Stash purchase (and similar) for footer print in same request.
	 *
	 * @param array<string, mixed> $payload Event payload.
	 */
	private function queueInlineEvent( array $payload ): void {
		$GLOBALS['consentaro_woo_inline']   = $GLOBALS['consentaro_woo_inline'] ?? array();
		$GLOBALS['consentaro_woo_inline'][] = $payload;
	}

	/**
	 * @return array<int, array<string, mixed>>
	 */
	private function consumeInlineQueue(): array {
		$queue = $GLOBALS['consentaro_woo_inline'] ?? array();
		unset( $GLOBALS['consentaro_woo_inline'] );
		return is_array( $queue ) ? $queue : array();
	}

	/**
	 * Plugin enabled flag.
	 */
	private function isEnabled(): bool {
		$settings = get_option( 'consentaro_settings', array() );
		if ( ! is_array( $settings ) ) {
			return true;
		}
		return ! isset( $settings['enabled'] ) || ! empty( $settings['enabled'] );
	}
}
