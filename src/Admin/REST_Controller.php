<?php
/**
 * REST API controller.
 *
 * @package Consentaro
 */

declare(strict_types=1);

namespace Consentaro\Admin;

use Consentaro\Consent\ConsentManager;
use Consentaro\Consent\ConsentMode;
use Consentaro\Core\ServiceContainer;
use Consentaro\Integration\GeoLocation;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

/**
 * Routes under /consentaro/v1/.
 */
final class REST_Controller {

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
	 * Register routes.
	 */
	public function register(): void {
		add_action( 'rest_api_init', array( $this, 'registerRoutes' ) );
	}

	/**
	 * Route map.
	 */
	public function registerRoutes(): void {
		register_rest_route(
			'consentaro/v1',
			'/settings',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( $this, 'getSettings' ),
					'permission_callback' => array( $this, 'canManage' ),
				),
				array(
					'methods'             => 'POST',
					'callback'            => array( $this, 'saveSettings' ),
					'permission_callback' => array( $this, 'canManage' ),
				),
			)
		);

		register_rest_route(
			'consentaro/v1',
			'/geo-check',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'geoCheck' ),
				'permission_callback' => array( $this, 'canManage' ),
			)
		);

		register_rest_route(
			'consentaro/v1',
			'/consent',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( $this, 'getConsent' ),
					'permission_callback' => '__return_true',
				),
				array(
					'methods'             => 'POST',
					'callback'            => array( $this, 'saveConsent' ),
					'permission_callback' => array( $this, 'verifyPublicNonce' ),
					'args'                => array(
						'action'  => array(
							'type'              => 'string',
							'required'          => false,
							'sanitize_callback' => 'sanitize_key',
						),
						'consent' => array(
							'type'     => 'object',
							'required' => false,
						),
					),
				),
			)
		);
	}

	/**
	 * manage_options gate.
	 */
	public function canManage(): bool {
		return current_user_can( 'manage_options' );
	}

	/**
	 * Public REST calls must send X-WP-Nonce for wp_rest.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return bool|WP_Error
	 */
	public function verifyPublicNonce( WP_REST_Request $request ) {
		$nonce = $request->get_header( 'X-WP-Nonce' );
		if ( ! is_string( $nonce ) || ! wp_verify_nonce( $nonce, 'wp_rest' ) ) {
			return new WP_Error(
				'consentaro_invalid_nonce',
				__( 'Invalid privacy nonce.', 'consentaro' ),
				array( 'status' => 403 )
			);
		}

		return true;
	}

	/**
	 * GET /settings.
	 */
	public function getSettings(): WP_REST_Response {
		/** @var Settings $settings */
		$settings = $this->container->get( 'settings' );
		return rest_ensure_response( $settings->getSettings() );
	}

	/**
	 * POST /settings.
	 *
	 * @param WP_REST_Request $request Request.
	 */
	public function saveSettings( WP_REST_Request $request ): WP_REST_Response {
		/** @var Settings $settings */
		$settings = $this->container->get( 'settings' );
		$data     = $request->get_json_params();
		if ( ! is_array( $data ) ) {
			$data = $request->get_params();
		}

		return rest_ensure_response( $settings->saveSettings( is_array( $data ) ? $data : array() ) );
	}

	/**
	 * GET /geo-check — admin test helper.
	 */
	public function geoCheck(): WP_REST_Response {
		/** @var GeoLocation $geo */
		$geo = $this->container->get( 'geo_location' );

		return rest_ensure_response(
			array(
				'country'            => $geo->getCountryCode(),
				'is_eu'              => $geo->isEU(),
				'should_show_banner' => $geo->shouldShowBanner(),
			)
		);
	}

	/**
	 * GET /consent — current states (defaults if unset).
	 */
	public function getConsent(): WP_REST_Response {
		/** @var ConsentManager $manager */
		$manager = $this->container->get( 'consent_manager' );

		return rest_ensure_response(
			array(
				'stored'  => $manager->hasStoredConsent(),
				'consent' => $manager->getCurrentStates(),
			)
		);
	}

	/**
	 * POST /consent — accept-all | deny-all | custom map.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function saveConsent( WP_REST_Request $request ) {
		/** @var ConsentManager $manager */
		$manager = $this->container->get( 'consent_manager' );
		/** @var ConsentMode $mode */
		$mode = $this->container->get( 'consent_mode' );

		$params = $request->get_json_params();
		if ( ! is_array( $params ) ) {
			$params = $request->get_params();
		}

		$action = isset( $params['action'] ) ? sanitize_key( (string) $params['action'] ) : '';

		switch ( $action ) {
			case 'accept-all':
				$states = $manager->acceptAll();
				break;
			case 'deny-all':
				$states = $manager->denyAll();
				break;
			case 'customize':
			case 'custom':
			case '':
				$incoming = array();
				if ( isset( $params['consent'] ) && is_array( $params['consent'] ) ) {
					$incoming = $params['consent'];
				} elseif ( is_array( $params ) ) {
					// Allow flat body: { "ad_storage": "granted", ... }.
					foreach ( ConsentMode::TYPES as $type ) {
						if ( isset( $params[ $type ] ) ) {
							$incoming[ $type ] = $params[ $type ];
						}
					}
				}

				if ( empty( $incoming ) && '' === $action ) {
					return new WP_Error(
						'consentaro_missing_consent',
						__( 'Provide action or consent map.', 'consentaro' ),
						array( 'status' => 400 )
					);
				}

				if ( empty( $incoming ) ) {
					$incoming = $mode->getDefaultStates();
				}

				$states = $manager->updateStates( $incoming );
				break;
			default:
				return new WP_Error(
					'consentaro_invalid_action',
					__( 'Unknown consent action.', 'consentaro' ),
					array( 'status' => 400 )
				);
		}

		return rest_ensure_response(
			array(
				'success' => true,
				'consent' => $states,
				'update'  => $mode->formatUpdatePayload( $states ),
			)
		);
	}
}
