<?php
/**
 * Registerable interface.
 *
 * @package TenUp\WPSnapshots
 */

namespace TenUp\WPSnapshots\Infrastructure;

/**
 * Registerable interface.
 *
 * @package TenUp\WPSnapshots
 */
interface Registerable {

	/**
	 * Registers the component or service.
	 *
	 * @return void
	 */
	public function register();
}
