<?php
/**
 * Module interface.
 *
 * @package TenUp\Snapshots
 */

namespace TenUp\Snapshots\Infrastructure;

/**
 * Module interface.
 *
 * @package TenUp\Snapshots
 */
interface Module {

	/**
	 * Registers the component or service.
	 *
	 * @return void
	 */
	public function register();
}
