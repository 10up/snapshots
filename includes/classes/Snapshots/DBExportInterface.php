<?php
/**
 * Dumper interface
 *
 * @package TenUp\WPSnapshots
 */

namespace TenUp\WPSnapshots\Snapshots;

use TenUp\WPSnapshots\Infrastructure\SharedService;

/**
 * Interface DBExportInterface
 *
 * @package TenUp\WPSnapshots
 */
interface DBExportInterface extends SharedService {

	/**
	 * Creates a DB dump.
	 *
	 * @param string $id The snapshot ID.
	 * @param array  $args The snapshot arguments.
	 *
	 * @return int The size of the created file.
	 */
	public function dump( string $id, array $args ): int;
}
