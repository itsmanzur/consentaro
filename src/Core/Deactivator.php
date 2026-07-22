<?php
/**
 * Deactivation handler.
 *
 * @package ConsentFlow
 */

declare(strict_types=1);

namespace ConsentFlow\Core;

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
