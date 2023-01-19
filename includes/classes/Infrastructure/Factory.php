<?php
/**
 * Factory interface.
 *
 * @package TenUp\WPSnapshots
 */

namespace TenUp\WPSnapshots\Infrastructure;

/**
 * Factory interface.
 *
 * @package TenUp\WPSnapshots\Infrastructure
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
