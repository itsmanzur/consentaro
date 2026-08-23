<?php
/**
 * Deactivation handler.
 *
 * @package Consentaro
 */

declare(strict_types=1);

namespace Consentaro\Core;

use Consentaro\Admin\ConsentLog;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Runs on plugin deactivation (keeps options for re-activation).
 */
final class Deactivator {

	/**
	 * Unschedule cron, flush rewrite rules.
	 */
	public static function deactivate(): void {
		$timestamp = wp_next_scheduled( ConsentLog::CRON_HOOK );
		if ( $timestamp ) {
			wp_unschedule_event( $timestamp, ConsentLog::CRON_HOOK );
		}

		flush_rewrite_rules();
	}
}
