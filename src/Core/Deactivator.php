<?php
/**
 * Deactivation handler.
 *
 * @package Consentaro
 */

declare(strict_types=1);

namespace Consentaro\Core;

/**
 * Runs on plugin deactivation (keeps options for re-activation).
 */
final class Deactivator {

	/**
	 * Flush rewrite rules only.
	 */
	public static function deactivate(): void {
		flush_rewrite_rules();
	}
}
