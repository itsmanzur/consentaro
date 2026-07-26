<?php
/**
 * PSR-4 autoloader for Consentaro\ → src/
 *
 * @package Consentaro
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

spl_autoload_register(
	static function ( string $class ): void {
		$prefix = 'Consentaro\\';
		if ( strncmp( $prefix, $class, strlen( $prefix ) ) !== 0 ) {
			return;
		}

		$relative = substr( $class, strlen( $prefix ) );
		$file     = CONSENTARO_PATH . 'src/' . str_replace( '\\', '/', $relative ) . '.php';

		if ( is_readable( $file ) ) {
			require $file;
		}
	}
);
