<?php
/**
 * Interface for Storage Connectors.
 *
 * @package TenUp\WPSnapshots
 */

namespace TenUp\WPSnapshots\Snapshots;

/**
 * Interface StorageConnectorInterface
 *
 * @package TenUp\WPSnapshots
 */
interface StorageConnectorInterface extends ConnectorInterface {

	/**
	 * Tests the Storage connection.
	 *
	 * @return bool
	 */
	public function test_connection();
}
