<?php
/**
 * Dumper interface
 *
 * @package TenUp\WPSnapshots
 */

namespace TenUp\WPSnapshots\Snapshots;

use TenUp\WPSnapshots\Infrastructure\SharedService;

/**
 * Interface DumperInterface
 *
 * @package TenUp\WPSnapshots
 */
interface DumperInterface extends SharedService {

	/**
	 * Creates a DB dump.
	 *
	 * @param string $id The snapshot ID.
	 * @param array  $args The snapshot arguments.
	 */
	public function dump( string $id, array $args );
}
