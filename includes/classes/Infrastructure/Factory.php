<?php
/**
 * Factory interface.
 *
 * @package TenUp\Snapshots
 */

namespace TenUp\Snapshots\Infrastructure;

/**
 * Factory interface.
 *
 * @package TenUp\Snapshots\Infrastructure
 */
interface Factory extends SharedService {

	/**
	 * Gets an instance.
	 *
	 * @param mixed ...$args Arguments to pass to the constructor.
	 *
	 * @return object
	 */
	public function get( ...$args ) : object;
}
