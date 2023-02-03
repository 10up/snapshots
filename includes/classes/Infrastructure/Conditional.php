<?php
/**
 * Conditional interface.
 *
 * @package TenUp\WPSnapshots
 */

namespace TenUp\WPSnapshots\Infrastructure;

/**
 * Conditional interface.
 *
 * @package TenUp\WPSnapshots
 */
interface Conditional {

	/**
	 * Determines whether the module should be loaded.
	 *
	 * @return bool
	 */
	public static function is_needed();
}
