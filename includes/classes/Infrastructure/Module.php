<?php
/**
 * Module interface.
 *
 * @package TenUp\WPSnapshots
 */

namespace TenUp\WPSnapshots\Infrastructure;

/**
 * Module interface.
 *
 * @package TenUp\WPSnapshots
 */
interface Module {

	/**
	 * Registers the component or service.
	 *
	 * @return void
	 */
	public function register();
}
