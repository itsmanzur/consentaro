<?php
/**
 * Simple PSR-11 style service container.
 *
 * @package Consentaro
 */

declare(strict_types=1);

namespace Consentaro\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use RuntimeException;

/**
 * Lazy service container with shared instances.
 */
final class ServiceContainer {

	/**
	 * Factories.
	 *
	 * @var array<string, callable>
	 */
	private array $factories = array();

	/**
	 * Resolved shared instances.
	 *
	 * @var array<string, mixed>
	 */
	private array $instances = array();

	/**
	 * Register a shared service factory.
	 *
	 * @param string   $id       Service id.
	 * @param callable $factory  Factory receiving this container.
	 */
	public function set( string $id, callable $factory ): void {
		$this->factories[ $id ] = $factory;
		unset( $this->instances[ $id ] );
	}

	/**
	 * Whether a service is registered.
	 */
	public function has( string $id ): bool {
		return isset( $this->factories[ $id ] ) || isset( $this->instances[ $id ] );
	}

	/**
	 * Resolve a shared service.
	 *
	 * @param string $id Service id.
	 * @return mixed
	 */
	public function get( string $id ) {
		if ( isset( $this->instances[ $id ] ) ) {
			return $this->instances[ $id ];
		}

		if ( ! isset( $this->factories[ $id ] ) ) {
			throw new RuntimeException(
				sprintf(
					/* translators: %s: service id */
					'Service "%s" is not registered.',
					esc_html( $id )
				)
			);
		}

		$this->instances[ $id ] = ( $this->factories[ $id ] )( $this );

		return $this->instances[ $id ];
	}

	/**
	 * Resolve a fresh (non-shared) instance via factory.
	 *
	 * @param string $id Service id.
	 * @return mixed
	 */
	public function factory( string $id ) {
		if ( ! isset( $this->factories[ $id ] ) ) {
			throw new RuntimeException(
				sprintf(
					/* translators: %s: service id */
					'Service "%s" is not registered.',
					esc_html( $id )
				)
			);
		}

		return ( $this->factories[ $id ] )( $this );
	}
}
