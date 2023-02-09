<?php
/**
 * Conditional interface.
 *
 * @package TenUp\Snapshots
 */

namespace TenUp\Snapshots\Infrastructure;

/**
 * Conditional interface.
 *
 * @package TenUp\Snapshots
 */
interface Conditional {

	/**
	 * Determines whether the module should be loaded.
	 *
	 * @return bool
	 */
	public static function is_needed();
}
