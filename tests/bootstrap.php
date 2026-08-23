<?php
/**
 * PHPUnit bootstrap — pure Brain Monkey unit tests, no real WP install/DB.
 *
 * @package Consentaro
 */

declare(strict_types=1);

// Plugin class files all guard on this; tests never load through the real
// plugin bootstrap, so it's never otherwise defined.
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/' );
}

require dirname( __DIR__ ) . '/vendor/autoload.php';
